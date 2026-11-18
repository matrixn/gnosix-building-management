<?php

namespace App\Http\Controllers\Identity;

use App\Assets\EvidenceBridge;
use App\Evidence\Engine;
use App\Field\Access;
use App\Field\Invitations;
use App\Field\QrRegistry;
use App\Field\Reports;
use App\Http\Controllers\Controller;
use App\Maintenance\Documents;
use App\Maintenance\Settings;
use App\Maintenance\Workflow;
use App\Models\AssetAudit;
use App\Models\Building;
use App\Models\Evidence\Version;
use App\Models\FieldInvitation;
use App\Models\Organization;
use App\Models\QrIdentity;
use App\Models\QrLocator;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\MaintenancePolicy;
use App\Support\CapabilityService;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class FieldController extends Controller
{
    public function entry(Request $r)
    {
        $r->session()->put('field_entry', true);

        return view('field.invitation');
    }

    public function preview(Request $r)
    {
        $d = $r->validate(['invitation_token' => 'required|string|size:64']);
        $invitation = app(Invitations::class)->preview($r->user('web'), $d['invitation_token']);

        return view('field.invitation-review', ['invitation' => $invitation, 'token' => $d['invitation_token']]);
    }

    public function redeem(Request $r)
    {
        $d = $r->validate(['invitation_token' => 'required|string|size:64']);
        app(Invitations::class)->redeem($r->user('web'), $d['invitation_token']);

        return redirect()->route('field.index')->with('status', __('field.redeemed'));
    }

    public function index(Request $r)
    {
        $u = $r->user('web');
        $r->session()->forget('field_entry');
        if ($l = $r->session()->pull('field_locator')) {
            return redirect()->route('field.qr.resolve', $l);
        }$r->validate(['q' => 'nullable|string|max:160', 'asset' => 'nullable|integer', 'building' => 'nullable|integer', 'space' => 'nullable|integer']);
        $page = app(Access::class)->candidates($u)->when($r->input('q'), fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$s.'%')->orWhere('reference', 'like', '%'.$s.'%')))->when($r->integer('asset'), fn ($q, $id) => $q->where('asset_id', $id))->when($r->integer('building'), fn ($q, $id) => $q->where('building_id', $id))->when($r->integer('space'), fn ($q, $id) => $q->where('space_id', $id))->with(['property', 'building', 'space', 'asset'])->latest('id')->simplePaginate(24)->withQueryString();
        $page->setCollection($page->getCollection()->filter(fn ($w) => app(Access::class)->context($u, $w)));

        return view('field.index', ['orders' => $page]);
    }

    private function work(Request $r, string $uuid, bool $write = false): array
    {
        $w = WorkOrder::where('uuid', $uuid)->firstOrFail();
        $ctx = app(Access::class)->authorize($r->user('web'), $w, $write);

        return [$w, $ctx];
    }

    private function documents(WorkOrder $w, array $ctx)
    {
        return Version::whereIn('building_id', $w->property->buildings()->select('id'))->whereIn('id', $ctx['external'] ? $ctx['grant']->documents()->whereNotNull('version_id')->select('version_id') : $w->attachments()->whereNotNull('version_id')->select('version_id'));
    }

    public function show(Request $r, string $uuid)
    {
        [$w,$ctx] = $this->work($r, $uuid);
        app(Workflow::class)->audit($w, $r->user('web'), 'field_access');
        $reports = $w->serviceReports()->when($ctx['external'], fn ($q) => $q->where('author_id', $r->user('web')->id)->where('field_invitation_id', $ctx['grant']->id))->latest('number')->paginate(10, ['*'], 'reports_page');
        $documents = $this->documents($w, $ctx)->with('document')->latest('id')->paginate(10, ['*'], 'documents_page');
        $history = WorkOrder::where('property_id', $w->property_id)->available()->where('asset_id', $w->asset_id ?? 0)->whereNotNull('resolved_at')->where('id', '!=', $w->id)->latest('resolved_at')->limit(20)->get()->filter(fn ($h) => app(Access::class)->context($r->user('web'), $h));
        $rules = app(Settings::class)->all();
        $repeat = $history->where('category', $w->category)->where('resolved_at', '>=', now()->subDays($rules['repeat_days']));
        $manager = app(MaintenancePolicy::class)->manage($r->user('web'), $w->property);
        $approvedWarranty = $w->asset?->applications()->where('field', 'warranty_end')->where('status', 'active')->with('link.assertion')->get()->first(fn ($a) => app(EvidenceBridge::class)->valid($a));

        return view('field.work', compact('w', 'ctx', 'reports', 'documents', 'history', 'repeat', 'rules', 'manager', 'approvedWarranty'));
    }

    public function action(Request $r, string $uuid, string $action)
    {
        [$w,$ctx] = $this->work($r, $uuid, true);
        $r->session()->setPreviousUrl(route('field.work', $uuid));
        if ($action === 'report') {
            $r->validate(['parts' => 'nullable|array|max:30']);
            app(Reports::class)->create($r->user('web'), $w, $r->all());
        } elseif ($action === 'upload') {
            $r->validate(['files' => 'required|array|min:1|max:5', 'files.*' => 'required|file']);
            app(Documents::class)->upload($r->user('web'), $w, $r->file('files'));
            if ($ctx['grant']) {
                foreach ($w->attachments()->where('uploaded_by', $r->user('web')->id)->whereNotNull('version_id')->get() as $a) {
                    $ctx['grant']->documents()->firstOrCreate(['source_uuid' => $a->source_uuid], ['version_id' => $a->version_id, 'created_at' => now()]);
                }
            }
        } elseif ($action === 'state') {
            $d = $r->validate(['state' => 'required|in:in_progress,awaiting_parts,external_service,blocked', 'revision' => 'required|integer|min:1', 'reason' => 'required|string|max:2000']);
            app(Workflow::class)->transition($r->user('web'), $w, $d);
        } else {
            abort(404);
        }

        return redirect()->route('field.work', $uuid)->with('status', __('field.saved'));
    }

    public function review(Request $r, string $uuid, string $report)
    {
        [$w] = $this->work($r, $uuid);
        $model = $w->serviceReports()->where('uuid', $report)->firstOrFail();
        app(Reports::class)->review($r->user('web'), $model, $r->all());

        return redirect()->route('field.work', $uuid)->with('status', __('field.saved'));
    }

    private function send(Version $v)
    {
        abort_unless(Building::whereKey($v->building_id)->where('state', '!=', 'archived')->exists(), 404);
        $disk = Storage::disk('evidence');
        abort_unless($disk->exists($v->path), 404);
        $bytes = $disk->get($v->path);
        abort_unless(hash_equals($v->sha256, hash('sha256', $bytes)), 409);

        return response($bytes, 200, ['Content-Type' => $v->mime, 'Content-Disposition' => HeaderUtils::makeDisposition('attachment', Engine::filename($v->original_name), 'document'), 'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    public function document(Request $r, string $uuid, string $version)
    {
        [$w,$ctx] = $this->work($r, $uuid);
        abort_unless($r->integer('viewer') === $r->user('web')->id, 403);
        $v = $this->documents($w, $ctx)->findOrFail($version);

        return $this->send($v);
    }

    public function reportPdf(Request $r, string $uuid, string $report)
    {
        [$w,$ctx] = $this->work($r, $uuid);
        abort_unless($r->integer('viewer') === $r->user('web')->id, 403);
        $model = $w->serviceReports()->where('uuid', $report)->firstOrFail();
        if ($ctx['external']) {
            abort_unless($model->author_id === $r->user('web')->id && $model->field_invitation_id === $ctx['grant']->id && (! $model->snapshot['costs_permitted'] || $ctx['costs']), 403);
        }$v = $model->reviews()->where('decision', 'accepted')->latest('sequence')->value('version_id') ?? $model->version_id;
        abort_unless($v, 404);

        return $this->send(Version::findOrFail($v));
    }

    public function resolve(Request $r, string $locator)
    {
        $l = QrLocator::where('locator', $locator)->whereNull('revoked_at')->first();
        $u = $r->user('web');
        if (! $u || $u->isArchived() || (int) $r->session()->get('customer_auth_version', 0) !== $u->auth_version) {
            $r->session()->put('field_entry', true);
            $r->session()->put('field_locator', $locator);

            return view('field.scan', ['available' => false]);
        }if (! $u->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }if (! $l || ! ($q = $l->identity) || ! ($target = $q->target()) || ! app(QrRegistry::class)->available($target, $q->target_type)) {
            return view('field.scan', ['available' => false]);
        }
        if (Gate::forUser($u)->allows('update', $q->property)) {
            $route = match ($q->target_type) {
                'asset' => Gate::forUser($u)->allows('view', $target) ? route('app.assets.show', [$q->organization, $target->uuid]) : null,'building' => route('app.declarations.show', [$q->organization, $q->property, $target->uuid]),default => route('app.maintenance.index', [$q->organization, 'property' => $q->property_id, 'building' => $target->building_id, 'space' => $q->space_id])
            };
            if ($route) {
                $this->scanAudit($u, $q);

                return redirect($route);
            }
        }
        $works = app(Access::class)->candidates($u)->where('property_id', $q->property_id)->when($q->asset_id, fn ($w) => $w->where('asset_id', $q->asset_id))->when($q->building_id, fn ($w) => $w->where('building_id', $q->building_id))->when($q->space_id, fn ($w) => $w->where('space_id', $q->space_id))->when($q->floor_id, fn ($w) => $w->whereHas('space', fn ($s) => $s->where('floor_id', $q->floor_id)))->simplePaginate(24)->withQueryString();
        $works->setCollection($works->getCollection()->filter(fn ($w) => app(Access::class)->context($u, $w)));
        if ($works->isEmpty()) {
            return view('field.scan', ['available' => false]);
        }$this->scanAudit($u, $q);

        return view('field.index', ['orders' => $works, 'scanName' => $target->name]);
    }

    private function scanAudit(User $u, QrIdentity $q): void
    {
        AssetAudit::create(['organization_id' => $q->organization_id, 'property_id' => $q->property_id, 'asset_id' => $q->asset_id, 'actor_id' => $u->id, 'entity_type' => 'qr', 'entity_uuid' => $q->uuid, 'action' => 'field.qr_scanned', 'after_data' => ['target_uuid' => $q->target_uuid], 'created_at' => now()]);
    }

    public function manage(Request $r, Organization $organization)
    {
        Gate::forUser($r->user('web'))->authorize('view', $organization);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'hospitality.maintenance'), 403);
        $p = $r->integer('property') ? $organization->properties()->findOrFail($r->integer('property')) : null;
        if ($p) {
            app(QrRegistry::class)->manage($r->user('web'), $p);
        }$invites = $p ? FieldInvitation::where('property_id', $p->id)->latest('id')->paginate(20) : null;

        return view('field.manage', ['organization' => $organization, 'property' => $p, 'invitations' => $invites]);
    }

    public function invite(Request $r, Organization $organization)
    {
        $d = $r->validate(['property_id' => 'required|integer']);
        $p = $organization->properties()->findOrFail($d['property_id']);
        [$invitation,$token] = app(Invitations::class)->create($r->user('web'), $p, $r->all());

        return response()->view('field.created', ['organization' => $organization, 'invitation' => $invitation, 'link' => rtrim(SiteSettings::value('canonical_url'), '/').'/field/invitation#'.$token])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function revoke(Request $r, Organization $organization, string $invitation)
    {
        $g = FieldInvitation::where('organization_id', $organization->id)->where('uuid', $invitation)->firstOrFail();
        app(Invitations::class)->revoke($r->user('web'), $g);

        return redirect()->route('app.field.manage', [$organization, 'property' => $g->property_id]);
    }

    public function share(Request $r, Organization $organization, string $invitation)
    {
        $g = FieldInvitation::where('organization_id', $organization->id)->where('uuid', $invitation)->firstOrFail();
        app(QrRegistry::class)->manage($r->user('web'), $g->property);
        $d = $r->validate(['version_id' => 'required|uuid']);
        $v = Version::whereIn('building_id', $g->property->buildings()->select('id'))->findOrFail($d['version_id']);
        app(Engine::class)->authorize($r->user('web'), Building::findOrFail($v->building_id));
        $g->documents()->firstOrCreate(['source_uuid' => $v->id], ['version_id' => $v->id, 'created_at' => now()]);
        app(Invitations::class)->audit($g, $r->user('web'), 'document_shared');

        return redirect()->route('app.field.manage', [$organization, 'property' => $g->property_id]);
    }

    public function unshare(Request $r, Organization $organization, string $invitation, int $document)
    {
        $g = FieldInvitation::where('organization_id', $organization->id)->where('uuid', $invitation)->firstOrFail();
        app(QrRegistry::class)->manage($r->user('web'), $g->property);
        $g->documents()->whereKey($document)->delete();
        app(Invitations::class)->audit($g, $r->user('web'), 'document_unshared');

        return redirect()->route('app.field.manage', [$organization, 'property' => $g->property_id]);
    }
}
