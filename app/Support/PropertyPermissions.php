<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PropertyPermissions
{
    public function visible(User $user, Organization $organization, bool $write = false): Builder
    {
        $query = Property::where('organization_id', $organization->id);
        $membership = $organization->memberships()->where('user_id', $user->id)->first();
        if (! $membership || ! Gate::forUser($user)->allows('view', $organization)) {
            return $query->whereRaw('1 = 0');
        }
        if ($write && in_array($membership->role, ['technician', 'reporter'], true)) {
            return $query->whereRaw('1=0');
        }
        if ($membership->role === 'owner') {
            return $query;
        }
        if ($membership->access_scope === 'all') {
            return ! $write || in_array($membership->role, ['admin', 'manager'], true) ? $query : $query->whereRaw('1=0');
        }

        return $query->whereIn('id', $membership->propertyAccesses()->whereIn('permission', $write ? ['editor'] : ['viewer', 'editor'])->select('property_id'));
    }

    public function can(User $user, Property $property, bool $write = false): bool
    {
        $organization = $property->organization;
        if (! app(CapabilityService::class)->allows($user, $organization, 'hospitality.buildings')) {
            return false;
        }
        $membership = $organization->memberships()->where('user_id', $user->id)->first();
        if (! $membership) {
            return false;
        }
        if ($write && in_array($membership->role, ['technician', 'reporter'], true)) {
            return false;
        }
        if ($membership->role === 'owner') {
            return true;
        }
        if ($membership->access_scope === 'all') {
            return ! $write || in_array($membership->role, ['admin', 'manager'], true);
        }

        return $membership->propertyAccesses()->where('property_id', $property->id)->whereIn('permission', $write ? ['editor'] : ['editor', 'viewer'])->exists();
    }
}
