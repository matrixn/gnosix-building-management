<?php

namespace App\Http\Controllers\Identity;

use App\Assets\EvidenceBridge;
use App\Assets\Photos;
use App\Assets\Registry;
use App\Evidence\Facts;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetEvidenceLink;
use App\Models\AssetPhoto;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Version;
use App\Models\Floor;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Space;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    private function organization(Request $r, Organization $o): void
    {
        Gate::forUser($r->user('web'))->authorize('view', $o);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $o, 'assets.view'), 403);
    }

    private function asset(Request $r, Organization $o, Asset $a, string $ability = 'view'): void
    {
        $this->organization($r, $o);
        if (! $r->isMethod('GET')) {
            $r->session()->setPreviousUrl(route($r->routeIs('app.assets.update') ? 'app.assets.edit' : 'app.assets.show', [$o, $a->uuid]));
        }abort_unless($a->organization_id === $o->id, 404);
        Gate::forUser($r->user('web'))->authorize($ability, $a);
    }

    private function property(Request $r, Organization $o, int $id, bool $write = false): Property
    {
        $p = Property::where('organization_id', $o->id)->findOrFail($id);
        Gate::forUser($r->user('web'))->authorize($write ? 'update' : 'view', $p);

        return $p;
    }

    public function index(Request $r, Organization $organization)
    {
        $this->organization($r, $organization);
        $f = $r->validate(['q' => 'nullable|string|max:160', 'property' => 'nullable|integer', 'building' => 'nullable|integer', 'space' => 'nullable|integer', 'type' => 'nullable|integer', 'state' => 'nullable|in:current,archived,all', 'status' => ['nullable', Rule::in(config('assets.statuses'))], 'system' => 'nullable|integer']);
        $props = app(PropertyPermissions::class)->visible($r->user('web'), $organization);
        $assets = Asset::where('organization_id', $organization->id)->whereIn('property_id', $props->select('id'))->available()->with(['type', 'property', 'building', 'space'])->when(($f['state'] ?? 'current') !== 'all', fn ($q) => ($f['state'] ?? 'current') === 'archived' ? $q->whereNotNull('archived_at') : $q->whereNull('archived_at'))->when($f['q'] ?? null, fn ($q, $text) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$text.'%')->orWhere('reference', 'like', '%'.$text.'%')->orWhere('external_reference', 'like', '%'.$text.'%')->orWhere('serial_key', 'like', '%'.Registry::serial($text).'%')->orWhereHas('applications', fn ($q) => $q->where('field', 'serial_number')->where('serial_key', 'like', '%'.Registry::serial($text).'%')->where('status', 'active'))))->when($f['property'] ?? null, fn ($q, $id) => $q->where('property_id', $id))->when($f['building'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q->where('building_id', $id)->orWhereHas('servedSpaces', fn ($q) => $q->where('building_id', $id))))->when($f['space'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q->where('space_id', $id)->orWhereHas('servedSpaces', fn ($q) => $q->where('spaces.id', $id))))->when($f['type'] ?? null, fn ($q, $id) => $q->where('asset_type_id', $id))->when($f['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->when($f['system'] ?? null, fn ($q, $id) => $q->where('system_id', $id))->orderByDesc('id')->paginate(24)->withQueryString();
        $types = $this->types($organization);
        $canCreate = app(CapabilityService::class)->allows($r->user('web'), $organization, 'assets.manage') && app(PropertyPermissions::class)->visible($r->user('web'), $organization, true)->exists();

        return view('assets.index', compact('organization', 'assets', 'types', 'canCreate'));
    }

    private function types(Organization $o)
    {
        return AssetType::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $o->id))->orderBy('name')->limit(110)->get();
    }

    public function create(Request $r, Organization $organization)
    {
        $this->organization($r, $organization);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'assets.manage'), 403);
        $data = $r->validate(['property' => 'nullable|integer', 'building' => 'nullable|integer', 'space' => 'nullable|integer']);
        $property = isset($data['property']) ? $this->property($r, $organization, $data['property'], true) : null;
        if ($property) {
            Gate::forUser($r->user('web'))->authorize('create', [Asset::class, $property]);
        }$asset = new Asset(['manual_data' => [], 'building_id' => $data['building'] ?? null, 'space_id' => $data['space'] ?? null]);
        if ($property && $asset->space_id) {
            $s = Space::where('property_id', $property->id)->findOrFail($asset->space_id);
            $asset->building_id = $s->building_id;
            $asset->floor_id = $s->floor_id;
        }if ($property && $asset->building_id) {
            $property->buildings()->findOrFail($asset->building_id);
        }$types = $this->types($organization);

        return view('assets.edit', compact('organization', 'property', 'asset', 'types'));
    }

    public function store(Request $r, Organization $organization, Registry $registry)
    {
        $this->organization($r, $organization);
        $d = $r->validate(['property_id' => 'required|integer']);
        $p = $this->property($r, $organization, $d['property_id'], true);
        $r->session()->setPreviousUrl(route('app.assets.create', [$organization, 'property' => $p->id]));
        $a = $registry->save($r->user('web'), $p, $r->all());

        return redirect()->route('app.assets.show', [$organization, $a->uuid])->with('status', __('asset.saved'));
    }

    public function edit(Request $r, Organization $organization, Asset $asset)
    {
        $this->asset($r, $organization, $asset, 'update');
        $property = $asset->property;
        $types = $this->types($organization);

        return view('assets.edit', compact('organization', 'property', 'asset', 'types'));
    }

    public function update(Request $r, Organization $organization, Asset $asset, Registry $registry)
    {
        $this->asset($r, $organization, $asset, 'update');
        $registry->save($r->user('web'), $asset->property, $r->all(), $asset);

        return redirect()->route('app.assets.show', [$organization, $asset->uuid])->with('status', __('asset.saved'));
    }

    public function show(Request $r, Organization $organization, Asset $asset, Registry $registry, EvidenceBridge $bridge)
    {
        $this->asset($r, $organization, $asset);
        $asset->load(['type', 'property', 'building', 'floor', 'space', 'system']);
        $canEvidence = app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.upload') || app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.review');
        $links = $canEvidence ? $asset->evidenceLinks()->with(['assertion.references.version.document'])->latest('id')->paginate(15, ['*'], 'evidence_page')->withQueryString() : null;
        $documents = $canEvidence ? $asset->documents()->with('version.document')->latest('id')->paginate(12, ['*'], 'documents_page')->withQueryString() : null;
        $applications = $asset->applications()->where('status', 'active')->with('link.assertion')->get()->keyBy('field');
        $photos = $asset->photos()->whereNull('archived_at')->get();
        $archivedPhotos = $asset->photos()->whereNotNull('archived_at')->get();
        $photoUrls = $photos->mapWithKeys(fn ($p) => [$p->id => URL::temporarySignedRoute('app.assets.photo', now()->addMinutes(10), [$organization, $asset->uuid, $p->uuid, 'viewer' => $r->user('web')->id], false)]);
        $history = $asset->audits()->with('actor')->latest('id')->paginate(20, ['*'], 'history_page')->withQueryString();
        $served = $asset->servedSpaces()->with('building')->paginate(20, ['*'], 'spaces_page');
        $components = $asset->components()->available()->with('type')->orderBy('id')->paginate(12, ['*'], 'components_page');
        $duplicates = $registry->duplicates($asset, $asset->manual_data['serial_number'] ?? null)->limit(5)->get();

        return view('assets.show', compact('organization', 'asset', 'links', 'documents', 'applications', 'photos', 'archivedPhotos', 'photoUrls', 'history', 'served', 'components', 'duplicates', 'bridge', 'canEvidence'));
    }

    public function archive(Request $r, Organization $organization, Asset $asset, Registry $registry)
    {
        $this->asset($r, $organization, $asset, 'update');
        $d = $r->validate(['revision' => 'required|integer|min:1', 'archived' => 'required|boolean']);
        $registry->archive($r->user('web'), $asset, $d['revision'], (bool) $d['archived']);

        return redirect()->route('app.assets.show', [$organization, $asset->uuid])->with('status', __('asset.saved'));
    }

    public function link(Request $r, Organization $organization, Asset $asset, EvidenceBridge $bridge)
    {
        $this->asset($r, $organization, $asset, 'update');
        $d = $r->validate(['revision' => 'required|integer|min:1', 'assertion_id' => 'required|uuid', 'confirmed' => 'required|accepted']);
        $fact = Assertion::findOrFail($d['assertion_id']);
        $bridge->link($r->user('web'), $asset, $fact, $d['revision']);

        return redirect()->route('app.assets.show', [$organization, $asset->uuid])->with('status', __('asset.linked'));
    }

    public function document(Request $r, Organization $organization, Asset $asset, EvidenceBridge $bridge)
    {
        $this->asset($r, $organization, $asset, 'update');
        $d = $r->validate(['revision' => 'required|integer|min:1', 'version_id' => 'required|uuid']);
        $bridge->document($r->user('web'), $asset, Version::findOrFail($d['version_id']), $d['revision']);

        return redirect()->route('app.assets.show', [$organization, $asset->uuid])->with('status', __('asset.linked'));
    }

    public function review(Request $r, Organization $organization, Asset $asset, AssetEvidenceLink $link, EvidenceBridge $bridge)
    {
        $this->asset($r, $organization, $asset, 'review');
        abort_unless($link->asset_id === $asset->id, 404);
        $d = $r->validate(['revision' => 'required|integer|min:1', 'source_revision' => 'nullable|integer|min:1', 'justification' => 'required|string|max:2000', 'replace' => 'sometimes|accepted', 'action' => 'required|in:apply,withdraw']);
        if ($d['action'] === 'withdraw') {
            $bridge->withdraw($r->user('web'), $asset, $link, $d['revision'], $d['justification']);
        } else {
            $r->validate(['source_revision' => 'required|integer|min:1']);
            $bridge->apply($r->user('web'), $asset, $link, $d['revision'], $d['source_revision'], $d['justification'], isset($d['replace']));
        }

        return redirect()->route('app.assets.show', [$organization, $asset->uuid])->with('status', __('asset.saved'));
    }

    public function photos(Request $r, Organization $organization, Asset $asset, Photos $photos)
    {
        $this->asset($r, $organization, $asset, 'update');
        $d = $r->validate(['revision' => 'required|integer|min:1', 'photos' => 'required|array|min:1|max:3', 'photos.*' => 'required|file', 'caption' => 'nullable|string|max:160']);
        $photos->upload($r->user('web'), $asset, $d['photos'], $d['revision'], $d['caption'] ?? null);

        return redirect()->route('app.assets.show', [$organization, $asset->uuid])->with('status', __('asset.saved'));
    }

    public function photo(Request $r, Organization $organization, Asset $asset, AssetPhoto $photo)
    {
        $this->asset($r, $organization, $asset);
        abort_unless($photo->asset_id === $asset->id && ! $photo->archived_at && (string) $r->query('viewer') === (string) $r->user('web')->id, 403);
        $bytes = Storage::disk('evidence')->get($photo->path);
        abort_unless(hash_equals($photo->sha256, hash('sha256', $bytes)), 409);

        return response($bytes)->header('Content-Type', $photo->mime)->header('Cache-Control', 'private, no-store')->header('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function photoArchive(Request $r, Organization $organization, Asset $asset, AssetPhoto $photo, Registry $registry)
    {
        $this->asset($r, $organization, $asset, 'update');
        $d = $r->validate(['revision' => 'required|integer|min:1', 'archived' => 'sometimes|boolean']);
        DB::transaction(function () use ($r, $asset, $photo, $d, $registry) {
            $a = $registry->lock($asset->property, $asset);
            $registry->writable($r->user('web'), $a);
            $registry->revision($a, $d['revision']);
            abort_unless($photo->asset_id === $a->id, 404);
            $archive = (bool) ($d['archived'] ?? true);
            $photo->update(['archived_at' => $archive ? now() : null]);
            $a->increment('revision');
            $registry->audit($a, $r->user('web'), $archive ? 'photo_archived' : 'photo_restored', null, ['photo_uuid' => $photo->uuid]);
        });

        return redirect()->route('app.assets.show', [$organization, $asset->uuid]);
    }

    public function lookup(Request $r, Organization $organization)
    {
        $this->organization($r, $organization);
        $d = $r->validate(['kind' => 'required|in:property,building,floor,space,system,asset,assertion,version', 'q' => 'nullable|string|max:160', 'property' => 'nullable|integer', 'building' => 'nullable|integer', 'floor' => 'nullable|integer', 'page' => 'nullable|integer|min:1|max:100000', 'write' => 'sometimes|boolean']);
        $kind = $d['kind'];
        $text = $d['q'] ?? '';
        if ($kind === 'property') {
            $q = app(PropertyPermissions::class)->visible($r->user('web'), $organization, (bool) ($d['write'] ?? false));
        } else {
            $p = $this->property($r, $organization, (int) ($d['property'] ?? 0));
            $q = match ($kind) {
                'building' => $p->buildings(),'floor' => Floor::whereHas('building', fn ($q) => $q->where('property_id', $p->id)),'space' => $p->spaces()->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building')),'system','asset' => Asset::where('property_id', $p->id)->available()->whereNull('archived_at'), 'assertion' => Assertion::whereIn('building_id', $p->buildings()->select('id')),'version' => Version::whereIn('building_id', $p->buildings()->select('id'))
            };
            if (in_array($kind, ['assertion', 'version'])) {
                abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.upload') || app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.review'), 403);
                $q->when($text, fn ($q) => $kind === 'assertion' ? $q->where(fn ($q) => $q->where('subject_reference', 'like', '%'.$text.'%')->orWhere('original_value', 'like', '%'.$text.'%')) : $q->where('original_name', 'like', '%'.$text.'%'));
            } else {
                $q->when($text, fn ($q) => $q->where(function ($q) use ($kind, $text) {
                    $q->where('name', 'like', '%'.$text.'%');
                    if (in_array($kind, ['system', 'asset'])) {
                        $q->orWhere('reference', 'like', '%'.$text.'%')->orWhere('serial_key', 'like', '%'.Registry::serial($text).'%')->orWhere('external_reference', 'like', '%'.$text.'%');
                    }
                }));
            }
            if ($kind === 'system') {
                $q->where('is_system', true);
            }if (in_array($kind, ['floor', 'space']) && isset($d['building'])) {
                $q->where('building_id', $d['building']);
            }if ($kind === 'space' && isset($d['floor'])) {
                $q->where('floor_id', $d['floor']);
            }
        }
        if ($kind === 'property' && $text) {
            $q->where('name', 'like', '%'.$text.'%');
        }$page = $q->orderBy('id')->simplePaginate(20);
        $items = $page->map(fn ($x) => ['id' => in_array($kind, ['assertion', 'version']) ? $x->id : $x->id, 'label' => match ($kind) {
            'assertion' => $x->subject_reference.' · '.__('evidence.key_'.$x->semantic_key).' · '.$x->original_value,'version' => $x->original_name.' · v'.$x->number,default => $x->name
        }, 'uuid' => $x->uuid ?? $x->id]);

        return response()->json(['items' => $items, 'more' => $page->hasMorePages()])->header('Cache-Control', 'no-store');
    }

    public function room(Request $r, Organization $organization, Property $property, Space $space)
    {
        $this->organization($r, $organization);
        abort_unless($property->organization_id === $organization->id && $space->property_id === $property->id, 404);
        Gate::forUser($r->user('web'))->authorize('view', $property);
        if ($space->building_id) {
            $property->buildings()->findOrFail($space->building_id);
        }$assets = Asset::where('property_id', $property->id)->available()->whereNull('archived_at')->where(fn ($q) => $q->where('space_id', $space->id)->orWhereHas('servedSpaces', fn ($q) => $q->where('spaces.id', $space->id)))->with('type')->orderBy('id')->simplePaginate(8);

        return response()->json(['items' => $assets->map(fn ($a) => ['name' => $a->name, 'reference' => $a->reference, 'type' => $a->type->label(), 'url' => route('app.assets.show', [$organization, $a->uuid])]), 'more' => $assets->hasMorePages(), 'all' => route('app.assets.index', [$organization, 'space' => $space->id]), 'create' => Gate::forUser($r->user('web'))->allows('create', [Asset::class, $property]) ? route('app.assets.create', [$organization, 'property' => $property->id, 'space' => $space->id]) : null])->header('Cache-Control', 'no-store');
    }

    public function typesIndex(Request $r, Organization $organization)
    {
        $this->organization($r, $organization);
        $this->typePermission($r, $organization);
        $types = AssetType::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organization->id))->orderBy('id')->paginate(20);

        return view('assets.types', compact('organization', 'types'));
    }

    private function typePermission(Request $r, Organization $o): void
    {
        Gate::forUser($r->user('web'))->authorize('update', $o);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $o, 'assets.types'), 403);
    }

    public function typeSave(Request $r, Organization $organization, ?AssetType $type = null)
    {
        $this->organization($r, $organization);
        $this->typePermission($r, $organization);
        $d = $r->validate(['name' => 'required|string|max:100', 'attribute_schema' => 'required|string|max:20000', 'revision' => $type ? 'required|integer|min:1' : 'nullable|integer', 'archived' => 'sometimes|boolean']);
        try {
            $schema = json_decode($d['attribute_schema'], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['attribute_schema' => __('asset.schema_invalid')]);
        }if (! is_array($schema) || ! array_is_list($schema) || count($schema) > 20) {
            throw ValidationException::withMessages(['attribute_schema' => __('asset.schema_invalid')]);
        }Validator::make(['fields' => $schema], ['fields.*' => 'array:key,label,type,unit', 'fields.*.key' => 'required|string|regex:/^[a-z][a-z0-9_]{0,39}$/|distinct', 'fields.*.label' => 'required|string|max:80', 'fields.*.type' => 'required|in:string,number,integer,boolean,date', 'fields.*.unit' => 'nullable|string|max:20'])->validate();
        DB::transaction(function () use ($organization, $r, $type, $d, $schema) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $this->typePermission($r, $organization);
            if ($type) {
                abort_unless($type->organization_id === $organization->id, 403);
                $type->refresh();
                if ($type->revision !== (int) $d['revision']) {
                    throw ValidationException::withMessages(['revision' => __('asset.stale')]);
                }$type->revision++;
            } else {
                if (AssetType::where('organization_id', $organization->id)->count() >= 100) {
                    throw ValidationException::withMessages(['name' => __('asset.type_limit')]);
                }$type = new AssetType(['organization_id' => $organization->id, 'uuid' => (string) Str::uuid(), 'key' => (string) Str::uuid()]);
            }$type->fill(['name' => Facts::clean($d['name'], 100), 'attribute_schema' => $schema, 'archived_at' => ($d['archived'] ?? false) ? now() : null])->save();
            AssetAudit::create(['organization_id' => $organization->id, 'actor_id' => $r->user('web')->id, 'action' => 'type_saved', 'after_data' => ['type_uuid' => $type->uuid, 'name' => $type->name, 'schema' => $schema], 'created_at' => now()]);
        });

        return redirect()->route('app.assets.types', $organization)->with('status', __('asset.saved'));
    }

    public function import(Request $r, Organization $organization)
    {
        $this->organization($r, $organization);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'assets.manage'), 403);
        $d = $r->validate(['candidate' => 'nullable|uuid', 'q' => 'nullable|string|max:100']);
        $buildings = Building::whereIn('property_id', app(PropertyPermissions::class)->visible($r->user('web'), $organization, true)->select('id'))->select('id');
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.upload') || app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.review'), 403);
        $base = Assertion::whereIn('building_id', $buildings)->where('subject_type', 'equipment')->whereNotNull('version_id')->whereIn('semantic_key', array_keys(config('assets.evidence_fields')))->whereIn('status', ['proposed', 'confirmed', 'disputed']);
        $groups = (clone $base)->when($d['q'] ?? null, fn ($q, $s) => $q->where('subject_reference', 'like', '%'.$s.'%'))->select('building_id', 'subject_reference')->selectRaw('MAX(id) AS candidate_uuid, COUNT(*) AS facts_count')->groupBy('building_id', 'subject_reference')->orderBy('building_id')->orderBy('subject_reference')->paginate(20)->withQueryString();
        $candidate = null;
        $facts = collect();
        $matches = collect();
        $types = $this->types($organization);
        $property = null;
        if (isset($d['candidate'])) {
            $candidate = (clone $base)->findOrFail($d['candidate']);
            $building = Building::findOrFail($candidate->building_id);
            $property = $building->property;
            $facts = (clone $base)->where('building_id', $building->id)->where('subject_reference', $candidate->subject_reference)->with('references.version.document')->latest()->limit(100)->get();
            $serials = $facts->where('semantic_key', 'equipment.serial_number')->map(fn ($f) => Registry::serial((string) $f->value))->filter(fn ($value) => $value !== null)->unique()->values();
            $matches = Asset::where('property_id', $property->id)->available()->where(fn ($q) => $q->where(fn ($q) => $q->where('building_id', $building->id)->where('external_reference', Registry::serial($candidate->subject_reference)))->when($serials->count(), fn ($q) => $q->orWhereIn('serial_key', $serials)->orWhereHas('applications', fn ($q) => $q->where('field', 'serial_number')->whereIn('serial_key', $serials)->where('status', 'active'))))->limit(10)->get();
        }

        return view('assets.import', compact('organization', 'groups', 'candidate', 'facts', 'matches', 'types', 'property'));
    }

    public function importCommit(Request $r, Organization $organization, Registry $registry, EvidenceBridge $bridge)
    {
        $this->organization($r, $organization);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'assets.manage'), 403);
        $d = $r->validate(['candidate' => 'required|uuid', 'ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|uuid|distinct', 'confirmed' => 'required|accepted', 'duplicates_ack' => 'sometimes|accepted', 'asset_id' => 'nullable|integer', 'asset_type_id' => 'nullable|integer', 'name' => 'nullable|string|max:150']);
        $a = DB::transaction(function () use ($d, $r, $organization, $registry, $bridge) {
            $base = Assertion::findOrFail($d['candidate']);
            $b = Building::findOrFail($base->building_id);
            $p = $this->property($r, $organization, $b->property_id, true);
            $registry->lock($p);
            $probe = new Asset(['property_id' => $p->id]);
            $bridge->source($r->user('web'), $probe, $base);
            $facts = Assertion::whereIn('id', $d['ids'])->where('building_id', $b->id)->where('subject_reference', $base->subject_reference)->where('subject_type', 'equipment')->whereNotNull('version_id')->whereIn('semantic_key', array_keys(config('assets.evidence_fields')))->whereIn('status', ['proposed', 'confirmed', 'disputed'])->get();
            if ($facts->count() !== count($d['ids'])) {
                throw ValidationException::withMessages(['ids' => __('asset.import_invalid')]);
            }
            if (isset($d['asset_id'])) {
                $a = Asset::where('property_id', $p->id)->findOrFail($d['asset_id']);
                $registry->writable($r->user('web'), $a);
            } else {
                if (! filled($d['name'] ?? null) || ! isset($d['asset_type_id'])) {
                    throw ValidationException::withMessages(['name' => __('asset.name_type_required')]);
                }$serials = $facts->where('semantic_key', 'equipment.serial_number')->map(fn ($f) => Registry::serial((string) $f->value))->filter(fn ($value) => $value !== null)->unique()->all();
                $duplicates = Asset::where('property_id', $p->id)->where(fn ($q) => $q->where(fn ($q) => $q->where('building_id', $b->id)->where('external_reference', Registry::serial($base->subject_reference)))->when(count($serials), fn ($q) => $q->orWhereIn('serial_key', $serials)->orWhereHas('applications', fn ($q) => $q->whereIn('serial_key', $serials)->where('field', 'serial_number')->where('status', 'active'))))->exists();
                if ($duplicates && ! isset($d['duplicates_ack'])) {
                    throw ValidationException::withMessages(['duplicates_ack' => __('asset.duplicate_ack')]);
                }$a = $registry->save($r->user('web'), $p, ['name' => $d['name'], 'asset_type_id' => $d['asset_type_id'], 'building_id' => $b->id, 'external_reference' => $base->subject_reference, 'status' => 'unknown']);
            }
            foreach ($facts as $f) {
                $bridge->link($r->user('web'), $a, $f, $a->fresh()->revision);
            }$registry->audit($a, $r->user('web'), 'imported', null, ['count' => $facts->count(), 'source' => 'existing_evidence']);

            return $a;
        });

        return redirect()->route('app.assets.show', [$organization, $a->uuid])->with('status',__('asset.imported'));
    }
}
