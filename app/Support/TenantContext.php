<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserPreference;

class TenantContext
{
    private ?Organization $active = null;

    public function resolve(User $user): void
    {
        $preferred = UserPreference::where('user_id', $user->id)->value('active_organization_id');
        $organizations = $user->organizations()->with('profile');
        $this->active = $preferred ? (clone $organizations)->where('organizations.id', $preferred)->first() : null;
        $this->active ??= $organizations->orderBy('organizations.id')->first();
        if ((int) $preferred !== (int) $this->active?->id) {
            UserPreference::updateOrCreate(['user_id' => $user->id], ['active_organization_id' => $this->active?->id]);
        }
    }

    public function current(): ?Organization { return $this->active; }
    public function clear(): void { $this->active = null; }
}
