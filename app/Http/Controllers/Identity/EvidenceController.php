<?php

namespace App\Http\Controllers\Identity;

use App\Evidence\Engine;
use App\Evidence\Providers;
use App\Evidence\Settings;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Audit;
use App\Models\Evidence\Conflict;
use App\Models\Evidence\Document;
use App\Models\Evidence\Version;
use App\Models\Organization;
use App\Models\Property;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvidenceController extends Controller
{
    private function authorize(Request $r, Organization $o, Property $p, Building $b, bool $write = false, string $cap = 'evidence.read'): void
    {
        abort_unless($p->organization_id === $o->id && $b->property_id === $p->id, 404);
        app(Engine::class)->authorize($r->user('web'), $b, $write, $cap);
    }

    public function index(Request $r, Organization $organization, PropertyPermissions $permissions)
    {
        Gate::forUser($r->user('web'))->authorize('view', $organization);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.upload') || app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.review'), 403);
        $buildings = Building::whereIn('property_id', $permissions->visible($r->user('web'), $organization)->select('id'))->with('property')->withCount(['floors', 'spaces'])->orderBy('name')->paginate(24);

        return view('evidence.index', compact('organization', 'buildings'));
    }

    public function show(Request $r, Organization $organization, Property $property, Building $building)
    {
        $this->authorize($r, $organization, $property, $building);
        $filters = $r->validate(['page' => 'nullable|integer|between:1,300', 'version' => 'nullable|uuid', 'fact' => 'nullable|uuid', 'status' => ['nullable', Rule::in(config('evidence.statuses'))]]);
        $inventory = Version::where('building_id', $building->id)->with(['document', 'extraction'])->latest()->orderByDesc('id')->paginate(12, ['*'], 'documents_page')->withQueryString();
        $selected = isset($filters['version']) ? Version::where('building_id', $building->id)->with(['document', 'extraction'])->findOrFail($filters['version']) : $inventory->first();
        $assertions = Assertion::where('building_id', $building->id)->with(['references.version.document', 'reviewer'])->when($filters['version'] ?? null, fn ($q, $id) => $q->where('version_id', $id))->when($filters['fact'] ?? null, fn ($q, $id) => $q->where('id', $id))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest()->orderByDesc('id')->paginate(20, ['*'], 'facts_page')->withQueryString();
        $conflicts = Conflict::where('building_id', $building->id)->where('status', 'open')->count();
        $confirmed = Assertion::where('building_id', $building->id)->where('status', 'confirmed')->count();
        $documentCount = Document::where('building_id', $building->id)->whereNull('archived_at')->count();
        $coverage = Document::where('building_id', $building->id)->whereNull('archived_at')->where('document_type', '!=', 'unknown')->distinct()->count('document_type');
        $timeline = Audit::where('building_id', $building->id)->with('actor')->latest('id')->paginate(20, ['*'], 'audit_page')->withQueryString();
        $settings = app(Settings::class)->all();
        unset($settings['api_key']);
        $available = app(Settings::class)->available();
        $formatSupported = ! $selected || Providers::supports($settings['provider'], $selected->mime);
        $canEdit = Gate::forUser($r->user('web'))->allows('update', $property) && $building->state !== 'archived';
        $canWrite = $canEdit && app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.upload');
        $canReview = $canEdit && app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.review');
        $canResolve = $canReview && app(CapabilityService::class)->allows($r->user('web'), $organization, 'evidence.conflicts');
        $preview = $selected ? URL::temporarySignedRoute('app.evidence.file', now()->addMinutes(10), [$organization, $property, $building->uuid, $selected->id, 'viewer' => $r->user('web')->id], false) : null;
        $conflictIds = Conflict::where('building_id', $building->id)->where('status', 'open')->get(['assertion_id', 'existing_assertion_id'])->flatMap(fn ($c) => [$c->assertion_id, $c->existing_assertion_id])->filter()->unique()->all();

        $initialPage = (int) ($filters['page'] ?? 1);

        return view('evidence.show', compact('initialPage', 'organization', 'property', 'building', 'inventory', 'selected', 'assertions', 'conflicts', 'confirmed', 'documentCount', 'coverage', 'timeline', 'settings', 'available', 'formatSupported', 'canWrite', 'canReview', 'canResolve', 'preview', 'conflictIds'));
    }

    public function upload(Request $r, Organization $organization, Property $property, Building $building, Engine $engine)
    {
        $this->authorize($r, $organization, $property, $building, true, 'evidence.upload');
        $data = $r->validate(['files' => 'required|array|max:5', 'files.*' => 'required|file', 'document_type' => ['required', Rule::in(config('evidence.document_types'))], 'document_id' => 'nullable|uuid']);
        $result = $engine->uploads($r->user('web'), $building, $data['files'], $data['document_type'], $data['document_id'] ?? null);

        return redirect()->route('app.evidence.show', [$organization, $property, $building->uuid, 'version' => $result[0]['version']->id])->with('status', __('evidence.uploaded', ['count' => count($result), 'duplicates' => collect($result)->where('duplicate', true)->count()]));
    }

    public function manual(Request $r, Organization $organization, Property $property, Building $building, Engine $engine)
    {
        $this->authorize($r, $organization, $property, $building, true, 'evidence.upload');
        $data = $r->validate(['version_id' => 'nullable|uuid']);
        $version = isset($data['version_id']) ? Version::where('building_id', $building->id)->findOrFail($data['version_id']) : null;
        $engine->propose($r->user('web'), $building, $r->only(['semantic_key', 'subject_type', 'subject_reference', 'space_id', 'value', 'quote', 'page', 'effective_on']), $version);

        return redirect()->route('app.evidence.show', [$organization, $property, $building->uuid])->with('status', __('evidence.proposed'));
    }

    public function review(Request $r, Organization $organization, Property $property, Building $building, string $assertion, Engine $engine)
    {
        $this->authorize($r, $organization, $property, $building, true, 'evidence.review');
        $d = $r->validate(['revision' => 'required|integer|min:1', 'action' => ['required', Rule::in(['confirm', 'reject', 'dispute', 'supersede', 'correct'])], 'justification' => 'nullable|string|max:2000', 'value' => 'nullable|string|max:4000']);
        $engine->review($r->user('web'), $building, $assertion, (int) $d['revision'], $d['action'], $d['justification'] ?? null, $d['value'] ?? null);

        return redirect()->route('app.evidence.show', [$organization, $property, $building->uuid])->with('status', __('evidence.reviewed'));
    }

    public function bulk(Request $r, Organization $organization, Property $property, Building $building, Engine $engine)
    {
        $this->authorize($r, $organization, $property, $building, true, 'evidence.review');
        $d = $r->validate(['action' => ['required', Rule::in(['confirm', 'reject'])], 'ids' => 'required|array|min:1|max:20', 'ids.*' => 'required|uuid|distinct']);
        DB::transaction(function () use ($d, $building, $r, $engine) {
            Property::whereKey($building->property_id)->lockForUpdate()->firstOrFail();
            foreach ($d['ids'] as $id) {
                $a = Assertion::where('building_id', $building->id)->findOrFail($id);
                if ($a->status !== 'proposed' || $engine->conflicts($a)->exists()) {
                    throw ValidationException::withMessages(['ids' => __('evidence.bulk_unsafe')]);
                }$engine->review($r->user('web'), $building, $id, $a->revision, $d['action']);
            }
        });

        return redirect()->route('app.evidence.show', [$organization, $property, $building->uuid])->with('status', __('evidence.reviewed'));
    }

    public function extract(Request $r, Organization $organization, Property $property, Building $building, string $version, Engine $engine)
    {
        $this->authorize($r, $organization, $property, $building, true, 'evidence.upload');
        $d = $r->validate(['consent' => 'required|accepted']);
        $v = Version::where('building_id', $building->id)->findOrFail($version);
        $engine->extract($r->user('web'), $building, $v, true);

        return redirect()->route('app.evidence.show', [$organization, $property, $building->uuid])->with('status', __('evidence.queued'));
    }

    public function archive(Request $r, Organization $organization, Property $property, Building $building, string $document, Engine $engine)
    {
        $this->authorize($r, $organization, $property, $building, true, 'evidence.upload');
        $d = $r->validate(['archived' => 'required|boolean']);
        $doc = Document::where('building_id', $building->id)->findOrFail($document);
        $engine->archive($r->user('web'), $building, $doc, (bool) $d['archived']);

        return redirect()->route('app.evidence.show', [$organization, $property, $building->uuid])->with('status', __('evidence.saved'));
    }

    public function file(Request $r, Organization $organization, Property $property, Building $building, string $version)
    {
        $this->authorize($r, $organization, $property, $building);
        abort_unless((string) $r->query('viewer') === (string) $r->user('web')->id, 403);
        $v = Version::where('building_id', $building->id)->findOrFail($version);
        abort_unless(Storage::disk('evidence')->exists($v->path), 404);
        abort_unless(hash_equals($v->sha256, hash('sha256', Storage::disk('evidence')->get($v->path))), 409);

        return Storage::disk('evidence')->response($v->path, $v->original_name, ['Content-Type' => $v->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"], 'attachment');
    }

    public function progress(Request $r, Organization $organization, Property $property, Building $building, string $version)
    {
        $this->authorize($r, $organization, $property, $building);
        $v = Version::where('building_id', $building->id)->findOrFail($version);
        $j = $v->extraction;

        return response()->json(['status' => $j?->status ?? 'not_requested', 'attempts' => $j?->attempts ?? 0, 'message' => __('evidence.job_'.($j?->status ?? 'not_requested'))])->header('Cache-Control', 'no-store');
    }
}
