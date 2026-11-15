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

    public function view(User $user, Organization $organization): bool { return in_array($this->role($user, $organization), \App\Models\OrganizationMembership::ROLES, true); }
    public function update(User $user, Organization $organization): bool { return in_array($this->role($user, $organization), ['owner', 'manager'], true); }
    public function manageMembers(User $user, Organization $organization): bool { return $this->role($user, $organization) === 'owner'; }
}
