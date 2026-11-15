<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function role(User $user, Organization $organization): ?string
    {
        return $organization->memberships()->where('user_id', $user->id)->value('role');
    }

    public function view(User $user, Organization $organization): bool
    {
        $membership = $organization->memberships()->where('user_id', $user->id)->first();
        return $membership && in_array($membership->role, \App\Models\OrganizationMembership::ROLES, true) && in_array($membership->access_scope, ['all', 'properties'], true);
    }
    public function update(User $user, Organization $organization): bool
    {
        $membership = $organization->memberships()->where('user_id', $user->id)->first();
        return $membership && ($membership->role === 'owner' || ($membership->access_scope === 'all' && in_array($membership->role, ['admin', 'manager'], true)));
    }
    public function manageMembers(User $user, Organization $organization): bool { return $this->role($user, $organization) === 'owner'; }
}
