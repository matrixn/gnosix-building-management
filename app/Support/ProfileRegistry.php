<?php

namespace App\Support;

class ProfileRegistry
{
    public function profiles(): array { return config('profiles.profiles'); }
    public function profile(?string $key): ?array { return $this->profiles()[$key ?? ''] ?? null; }
    public function enabled(?string $key): bool { return (bool) ($this->profile($key)['enabled'] ?? false); }
    public function capabilities(): array { return config('profiles.capabilities'); }
    public function capability(string $key): ?array { return $this->capabilities()[$key] ?? null; }
    public function kinds(?string $profile): array { return $this->profile($profile)['kinds'] ?? []; }
    public function navigation(): array { return config('profiles.navigation'); }

    public function onboardingKinds(): array
    {
        $kinds = [];
        foreach ($this->profiles() as $key => $profile) {
            foreach ($profile['kinds'] as $kind) $kinds[$kind][] = $key;
        }
        return $kinds;
    }

    public function validationRules(?string $profile, bool $complete): array
    {
        return [
            'profile_key' => [$complete ? 'required' : 'nullable', 'string', function ($attribute, $value, $fail) {
                if (! is_string($value) || ! $this->enabled($value)) $fail(__('workspace.profile_unavailable'));
            }],
            'organization_kind' => [$complete ? 'required' : 'nullable', 'string', \Illuminate\Validation\Rule::in($this->kinds($profile))],
            'name' => [$complete ? 'required' : 'nullable', 'string', 'max:150'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
