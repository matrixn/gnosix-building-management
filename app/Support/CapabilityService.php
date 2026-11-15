<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CapabilityService
{
    public function __construct(private ProfileRegistry $registry) {}

    public function allows(User $user, Organization $organization, string $key): bool
    {
        return $this->forOrganization($user, $organization)[$key]['allowed'] ?? false;
    }

    public function forOrganization(User $user, Organization $organization): array
    {
        if (! Gate::forUser($user)->allows('view', $organization)) return [];
        $profile = $organization->profile()->first();
        $overrides = $organization->capabilityOverrides()->pluck('enabled', 'capability');
        $result = [];
        foreach ($this->registry->profile($profile?->profile_key)['capabilities'] ?? [] as $key) {
            $definition = $this->registry->capability($key) ?? ['enabled' => false, 'operational' => false, 'icon' => '◇'];
            $allowed = $this->registry->enabled($profile?->profile_key) && $definition['enabled'] && (bool) ($overrides[$key] ?? true);
            $result[$key] = array_merge($definition, ['allowed' => $allowed]);
        }
        return $result;
    }
}
