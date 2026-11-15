<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\CapabilityService;
use App\Support\OrganizationOnboarding;
use App\Support\ProfileRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        return view('organizations.index', ['organizations' => $request->user('web')->organizations()->with('profile')->orderBy('name')->get()]);
    }

    public function create(Request $request, OrganizationOnboarding $onboarding, ProfileRegistry $registry)
    {
        return view('organizations.create', ['draft' => $onboarding->resume($request->user('web'), true), 'registry' => $registry]);
    }

    public function save(Request $request, OrganizationOnboarding $onboarding, ProfileRegistry $registry)
    {
        $key = $request->input('profile_key');
        $data = $request->validate($registry->validationRules(is_string($key) ? $key : null, false));
        $draft = $onboarding->save($request->user('web'), $data);
        return $request->expectsJson() ? response()->json(['version' => $draft->version, 'step' => $draft->current_step]) : back()->with('status', __('workspace.draft_saved'));
    }

    public function store(Request $request, OrganizationOnboarding $onboarding, ProfileRegistry $registry)
    {
        $key = $request->input('profile_key');
        $data = $request->validate($registry->validationRules(is_string($key) ? $key : null, true));
        $onboarding->save($request->user('web'), $data, true);
        return redirect()->route('app.dashboard')->with('status', __('workspace.organization_created'));
    }

    public function show(Request $request, Organization $organization, ProfileRegistry $registry, CapabilityService $capabilities)
    {
        Gate::forUser($request->user('web'))->authorize('view', $organization);
        return view('organizations.show', ['organization' => $organization->load('profile'), 'registry' => $registry, 'capabilities' => $capabilities->forOrganization($request->user('web'), $organization), 'memberships' => $organization->memberships()->with('user')->get()]);
    }

    public function switch(Request $request, Organization $organization)
    {
        Gate::forUser($request->user('web'))->authorize('view', $organization);
        UserPreference::updateOrCreate(['user_id' => $request->user('web')->id], ['active_organization_id' => $organization->id]);
        return redirect()->route('app.dashboard')->with('status', __('workspace.organization_switched'));
    }

    public function update(Request $request, Organization $organization, ProfileRegistry $registry)
    {
        Gate::forUser($request->user('web'))->authorize('update', $organization);
        $key = $request->input('profile_key');
        $rules = $registry->validationRules(is_string($key) ? $key : null, true);
        unset($rules['version']);
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $organization, $data, $registry) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user('web'))->authorize('update', $organization);
            $organization->update(['name' => $data['name']]);
            $organization->profile()->updateOrCreate(['organization_id' => $organization->id], ['profile_key' => $data['profile_key'], 'organization_kind' => $data['organization_kind'], 'schema_version' => $registry->profile($data['profile_key'])['schema_version']]);
            // No domain records, memberships or capability overrides are deleted.
        });
        return back()->with('status', __('workspace.profile_saved'));
    }

    public function capability(Request $request, Organization $organization, string $capability, CapabilityService $capabilities)
    {
        abort_unless($capabilities->allows($request->user('web'), $organization, $capability), 403);
        $definition = app(ProfileRegistry::class)->capability($capability);
        if ($request->expectsJson()) return response()->json(['organization_id' => $organization->id, 'capability' => $capability, 'allowed' => true, 'operational' => $definition['operational']]);
        if (($definition['operational'] ?? false) && isset($definition['route'])) return redirect()->route($definition['route'], $organization);
        return view('organizations.module', compact('organization', 'capability', 'definition'));
    }

    public function configureCapability(Request $request, Organization $organization, ProfileRegistry $registry)
    {
        Gate::forUser($request->user('web'))->authorize('update', $organization);
        $data = $request->validate(['capability' => ['required', 'string', 'max:100'], 'enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $organization, $registry, $data) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user('web'))->authorize('update', $organization);
            $profile = $organization->profile()->firstOrFail();
            if (! in_array($data['capability'], $registry->profile($profile->profile_key)['capabilities'] ?? [], true) || ! ($registry->capability($data['capability'])['enabled'] ?? false)) {
                throw ValidationException::withMessages(['capability' => __('workspace.capability_unavailable')]);
            }
            $organization->capabilityOverrides()->updateOrCreate(['capability' => $data['capability']], ['enabled' => $request->boolean('enabled'), 'updated_by' => $request->user('web')->id]);
        });
        return back()->with('status', __('workspace.capability_saved'));
    }

    public function addMember(Request $request, Organization $organization)
    {
        Gate::forUser($request->user('web'))->authorize('manageMembers', $organization);
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255'], 'role' => ['required', 'in:admin,manager,member']]);
        DB::transaction(function () use ($request, $organization, $data) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user('web'))->authorize('manageMembers', $organization);
            $member = User::where('email', strtolower($data['email']))->whereNotNull('email_verified_at')->first();
            if (! $member) throw ValidationException::withMessages(['email' => __('workspace.member_not_available')]);
            $organization->memberships()->firstOrCreate(['user_id' => $member->id], ['role' => $data['role']]);
        });
        return back()->with('status', __('workspace.member_saved'));
    }

    public function updateMember(Request $request, Organization $organization, OrganizationMembership $membership)
    {
        $data = $request->validate(['role' => ['required', 'in:admin,manager,member']]);
        $this->mutateMember($request, $organization, $membership, $data['role']);
        return back()->with('status', __('workspace.member_saved'));
    }

    public function removeMember(Request $request, Organization $organization, OrganizationMembership $membership)
    {
        $this->mutateMember($request, $organization, $membership, null);
        return back()->with('status', __('workspace.member_removed'));
    }

    private function mutateMember(Request $request, Organization $organization, OrganizationMembership $membership, ?string $role): void
    {
        DB::transaction(function () use ($request, $organization, $membership, $role) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user('web'))->authorize('manageMembers', $organization);
            $membership = $organization->memberships()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            abort_if($membership->role === 'owner', 403);
            if ($role) $membership->update(['role' => $role]);
            else {
                UserPreference::where('user_id', $membership->user_id)->where('active_organization_id', $organization->id)->update(['active_organization_id' => null]);
                $membership->delete();
            }
        });
    }
}
