<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\WorkspaceGroup;
use App\Support\CapabilityService;
use App\Support\WorkspacePreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkspaceSettingsController extends Controller
{
    public function show(Request $request, Organization $organization)
    {
        Gate::forUser($request->user('web'))->authorize('update', $organization);
        $organization->load(['groups', 'legalEntities', 'properties', 'memberships.user', 'memberships.propertyAccesses']);
        return view('studio.settings', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        Gate::forUser($request->user('web'))->authorize('update', $organization);
        if (is_string($request->input('country'))) $request->merge(['country' => strtoupper($request->input('country'))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'legal_name' => ['nullable', 'string', 'max:150'], 'country' => WorkspacePreferences::countryRules(), 'timezone' => WorkspacePreferences::timezoneRules(), 'locale' => ['required', Rule::in(['ro', 'en'])], 'unit_system' => ['required', Rule::in(['metric', 'imperial'])], 'contact_email' => ['nullable', 'string', 'email:rfc', 'max:255'], 'contact_phone' => ['nullable', 'string', new \App\Rules\ContactPhone], 'contact_address' => ['nullable', 'string', 'max:500'], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'], 'remove_logo' => ['sometimes', 'boolean']]);
        $newPath = $request->hasFile('logo') ? $request->file('logo')->store('organization-logos/'.$organization->id, 'local') : null;
        if ($request->hasFile('logo')) abort_unless(is_string($newPath) && $newPath !== '', 500);
        try {
            $oldPath = DB::transaction(function () use ($request, $organization, $data, $newPath) {
                $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($request->user('web'))->authorize('update', $organization);
                $old = $organization->logo_path;
                $changes = collect($data)->except(['logo', 'remove_logo'])->all();
                if (filled($changes['contact_phone'] ?? null)) $changes['contact_phone'] = \App\Rules\ContactPhone::normalize($changes['contact_phone']);
                if ($newPath || $request->boolean('remove_logo')) $changes['logo_path'] = $newPath;
                $organization->update($changes);
                return $old;
            });
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('local')->delete($newPath);
            throw $exception;
        }
        if (($newPath || $request->boolean('remove_logo')) && $oldPath && $oldPath !== $newPath) Storage::disk('local')->delete($oldPath);
        return back()->with('status', __('studio.saved'));
    }

    public function logo(Request $request, Organization $organization)
    {
        Gate::forUser($request->user('web'))->authorize('view', $organization);
        abort_unless($organization->logo_path && Storage::disk('local')->exists($organization->logo_path), 404);
        return Storage::disk('local')->response($organization->logo_path, 'organization-logo');
    }

    private function structure(Request $request, Organization $organization): void
    {
        Gate::forUser($request->user('web'))->authorize('update', $organization);
        abort_unless(app(CapabilityService::class)->allows($request->user('web'), $organization, 'hospitality.buildings'), 403);
    }

    public function group(Request $request, Organization $organization, ?WorkspaceGroup $group = null)
    {
        $this->structure($request, $organization);
        if ($group) abort_unless($group->organization_id === $organization->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'kind' => ['required', Rule::in(['group', 'brand', 'portfolio'])]]);
        $group ? $group->update($data) : $organization->groups()->create($data);
        return back()->with('status', __('studio.saved'));
    }

    public function entity(Request $request, Organization $organization, ?LegalEntity $entity = null)
    {
        $this->structure($request, $organization);
        if ($entity) abort_unless($entity->organization_id === $organization->id, 404);
        if (is_string($request->input('country'))) $request->merge(['country' => strtoupper($request->input('country'))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'country' => WorkspacePreferences::countryRules(false)]);
        $entity ? $entity->update($data) : $organization->legalEntities()->create($data);
        return back()->with('status', __('studio.saved'));
    }

    public function access(Request $request, Organization $organization, OrganizationMembership $membership)
    {
        Gate::forUser($request->user('web'))->authorize('manageMembers', $organization);
        abort_unless($membership->organization_id === $organization->id, 404);
        abort_if($membership->role === 'owner', 403);
        $data = $request->validate(['access_scope' => ['required', Rule::in(['all', 'properties'])], 'permission' => ['required', Rule::in(['viewer', 'editor'])], 'property_ids' => ['sometimes', 'array', 'max:1000'], 'property_ids.*' => ['integer', 'distinct', Rule::exists('properties', 'id')->where('organization_id', $organization->id)]]);
        DB::transaction(function () use ($request, $organization, $membership, $data) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user('web'))->authorize('manageMembers', $organization);
            $membership = $organization->memberships()->whereKey($membership->id)->lockForUpdate()->firstOrFail();
            abort_if($membership->role === 'owner', 403);
            $membership->update(['access_scope' => $data['access_scope']]);
            $membership->propertyAccesses()->delete();
            if ($data['access_scope'] === 'properties') foreach ($data['property_ids'] ?? [] as $id) $membership->propertyAccesses()->create(['property_id' => $id, 'permission' => $data['permission']]);
        });
        return back()->with('status', __('studio.saved'));
    }
}
