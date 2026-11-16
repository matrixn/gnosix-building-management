<?php

namespace App\Http\Controllers\Identity;

use App\Field\QrRegistry;
use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Organization;
use App\Models\QrIdentity;
use App\Support\CapabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QrController extends Controller
{
    public function index(Request $r, Organization $organization)
    {
        $u = $r->user('web');
        Gate::forUser($u)->authorize('view', $organization);
        abort_unless(app(CapabilityService::class)->allows($u, $organization, 'hospitality.maintenance'), 403);
        $r->validate(['property' => 'nullable|integer', 'type' => 'nullable|in:asset,building,floor,space', 'q' => 'nullable|string|max:160']);
        $kind = $r->input('type', 'asset');
        $p = $r->integer('property') ? $organization->properties()->findOrFail($r->integer('property')) : null;
        if (! $p) {
            return view('field.qr', ['organization' => $organization, 'property' => null, 'kind' => $kind, 'targets' => null, 'codes' => collect()]);
        }app(QrRegistry::class)->manage($u, $p);
        $query = match ($kind) {
            'asset' => $p->assets()->available()->whereNull('archived_at'),'building' => $p->buildings()->where('state', '!=', 'archived'),'space' => $p->spaces()->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building', fn ($q) => $q->where('state', '!=', 'archived'))),'floor' => Floor::whereHas('building', fn ($q) => $q->where('property_id', $p->id)->where('state', '!=', 'archived'))
        };
        $targets = $query->when($r->input('q'), fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))->orderBy('id')->paginate(24)->withQueryString();
        $codes = QrIdentity::where('property_id', $p->id)->where('target_type', $kind)->whereIn('target_uuid', $targets->pluck('uuid'))->with('locators')->get()->keyBy('target_uuid');

        return view('field.qr', ['organization' => $organization, 'property' => $p, 'kind' => $kind, 'targets' => $targets, 'codes' => $codes]);
    }

    public function mutate(Request $r, Organization $organization)
    {
        $d = $r->validate(['property_id' => 'required|integer', 'type' => 'required|in:asset,building,floor,space', 'target' => 'required|uuid', 'action' => 'required|in:generate,rotate,revoke']);
        $p = $organization->properties()->findOrFail($d['property_id']);
        app(QrRegistry::class)->generate($r->user('web'), $p, $d['type'], $d['target'], $d['action']);

        return redirect()->route('app.qr.index', [$organization, 'property' => $p->id, 'type' => $d['type']]);
    }

    public function image(Request $r, Organization $organization, string $identity, string $format)
    {
        $id = QrIdentity::where('organization_id', $organization->id)->where('uuid', $identity)->firstOrFail();
        app(QrRegistry::class)->manage($r->user('web'), $id->property);
        $l = $id->active();
        abort_unless($l && app(QrRegistry::class)->available($id->target(), $id->target_type), 404);
        abort_unless(in_array($format, ['png', 'svg']), 404);

        return response(app(QrRegistry::class)->render($l, $format), 200, ['Content-Type' => $format === 'png' ? 'image/png' : 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="GNOSIX-'.$id->uuid.'.'.$format.'"', 'Cache-Control' => 'no-store, private']);
    }

    public function labels(Request $r, Organization $organization)
    {
        $d = $r->validate(['property_id' => 'required|integer', 'type' => 'required|in:asset,building,floor,space', 'targets' => 'required|array|min:1|max:100', 'targets.*' => 'required|uuid|distinct']);
        $p = $organization->properties()->findOrFail($d['property_id']);
        app(QrRegistry::class)->manage($r->user('web'), $p);
        $labels = DB::transaction(function () use ($r, $p, $d) {
            $result = [];
            foreach ($d['targets'] as $uuid) {
                $id = app(QrRegistry::class)->generate($r->user('web'), $p, $d['type'], $uuid);
                $target = $id->target();
                $result[] = ['name' => $target->name, 'reference' => $target->reference ?? strtoupper(substr(str_replace('-', '', $uuid), 0, 12)), 'svg' => app(QrRegistry::class)->render($id->active())];
            }

            return $result;
        });

        return view('field.labels', compact('labels'));
    }
}
