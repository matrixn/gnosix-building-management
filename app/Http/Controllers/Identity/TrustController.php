<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Organization;
use App\Models\Property;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use App\Support\SiteSettings;
use App\Trust\Exports;
use App\Trust\Handover;
use App\Trust\Passport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TrustController extends Controller
{
    public function __construct(private Passport $passport, private Handover $handover) {}

    public function index(Request $request, Organization $organization, PropertyPermissions $permissions)
    {
        $user = $request->user('web');
        abort_unless(app(CapabilityService::class)->allows($user, $organization, 'trust.view'), 403);
        $buildings = Building::whereIn('property_id', $permissions->visible($user, $organization)->select('properties.id'))
            ->where('state', '!=', 'archived')->with('property:id,name')->orderBy('name')->orderBy('id')->simplePaginate(20);

        return view('trust.index', compact('organization', 'buildings'));
    }

    public function show(Request $request, Organization $organization, Property $property, Building $building)
    {
        $user = $request->user('web');
        $allowed = $this->passport->authorize($user, $organization, $property, $building);
        $passport = $this->passport->build($building, $organization, $allowed);
        $canManage = Gate::forUser($user)->allows('update', $property)
            && app(CapabilityService::class)->allows($user, $organization, 'trust.manage');
        $flows = $canManage ? DB::table('building_handovers')->where('source_organization_id', $organization->id)
            ->where('building_id', $building->id)->latest()->simplePaginate(12) : null;

        return view('trust.passport', compact('organization', 'property', 'building', 'passport', 'canManage', 'flows'));
    }

    public function export(Request $request, Organization $organization, Property $property, Building $building, string $format, Exports $exports)
    {
        $allowed = $this->passport->authorize($request->user('web'), $organization, $property, $building);

        return $exports->download($this->passport->build($building, $organization, $allowed), $format);
    }

    public function draft(Request $request, Organization $organization, Property $property, Building $building)
    {
        $flow = $this->handover->draft($request->user('web'), $organization, $property, $building);

        return redirect()->route('app.trust.flow', [$organization, $property, $building->uuid, $flow]);
    }

    public function flow(Request $request, Organization $organization, Property $property, Building $building, string $flow)
    {
        $allowed = $this->passport->authorize($request->user('web'), $organization, $property, $building, true);
        $flow = $this->handover->locate($organization, $building, $flow);
        $editable = in_array($flow->state, ['draft', 'prepared'], true);
        $selection = json_decode($flow->selection ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        $packet = $flow->snapshot && ! array_diff($selection['sections'], array_keys(array_filter($allowed)))
            ? $this->handover->packet($flow, $building) : null;
        $docs = $editable && $allowed['evidence'] ? $this->passport->documents($building)->with('document:id,document_type')
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(30, ['*'], 'documents') : null;
        $selected = array_filter((array) $request->old('versions', $selection['versions'] ?? []), 'is_string');
        $otherDocs = $docs ? $this->passport->documents($building)->whereIn('id', array_slice($selected, 0, Passport::SAMPLE))
            ->whereNotIn('id', $docs->pluck('id'))->get() : collect();
        $recipients = DB::table('handover_recipients as r')->leftJoin('building_stewardships as s', 's.recipient_id', '=', 'r.id')
            ->where('r.handover_id', $flow->id)->orderBy('r.created_at')
            ->get(['r.id', 'r.email', 'r.expires_at', 'r.redeemed_at', 'r.revoked_at', 'r.decision', 's.starts_at', 's.ends_at']);
        $events = $this->handover->history($flow->id)->simplePaginate(25, ['*'], 'events');

        return view('trust.flow', compact('organization', 'property', 'building', 'flow', 'editable', 'selection', 'packet', 'allowed', 'docs', 'otherDocs', 'recipients', 'events'));
    }

    public function action(Request $request, Organization $organization, Property $property, Building $building, string $flow, string $action)
    {
        $token = $this->handover->sourceAction($request->user('web'), $organization, $property, $building, $flow, $action, $request->all());
        $back = route('app.trust.flow', [$organization, $property, $building->uuid, $flow]);
        if ($token !== null) {
            $link = rtrim(SiteSettings::value('canonical_url'), '/').route('handover.invitation', absolute: false).'#'.$token;

            return response()->view('trust.invite-issued', compact('back', 'link'))->header('Cache-Control', 'private, no-store');
        }

        return redirect($back)->with('status', __('trust.saved'));
    }
}
