<?php

namespace App\Support;

use App\Models\OnboardingSession;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationOnboarding
{
    public function resume(User $user, bool $new = false): OnboardingSession
    {
        return DB::transaction(function () use ($user, $new) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $draft = OnboardingSession::firstOrCreate(['user_id' => $user->id], ['state' => [], 'current_step' => 'profile', 'version' => 1]);
            if ($new && $draft->completed_at) {
                $draft->update(['state' => [], 'current_step' => 'profile', 'organization_id' => null, 'completed_at' => null, 'version' => $draft->version + 1]);
            }
            return $draft;
        });
    }

    public function save(User $user, array $data, bool $complete = false): OnboardingSession
    {
        return DB::transaction(function () use ($user, $data, $complete) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $draft = OnboardingSession::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($draft->completed_at && $complete) {
                abort_unless($user->organizations()->where('organizations.id', $draft->organization_id)->exists(), 403);
                return $draft;
            }
            if ($draft->completed_at || $draft->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['draft' => __('workspace.draft_conflict')]);
            }
            $state = array_intersect_key($data, array_flip(['profile_key', 'organization_kind', 'name']));
            $step = empty($state['profile_key']) ? 'profile' : (empty($state['organization_kind']) ? 'kind' : 'name');
            $draft->fill(['state' => $state, 'current_step' => $step, 'version' => $draft->version + 1]);
            if ($complete) {
                $organization = Organization::create(['name' => $state['name']]);
                $organization->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
                $organization->profile()->create(['profile_key' => $state['profile_key'], 'organization_kind' => $state['organization_kind'], 'schema_version' => app(ProfileRegistry::class)->profile($state['profile_key'])['schema_version']]);
                UserPreference::updateOrCreate(['user_id' => $user->id], ['active_organization_id' => $organization->id]);
                $user->forceFill(['onboarding_completed_at' => $user->onboarding_completed_at ?? now(), 'profile_type' => $user->profile_type ?? 'owner'])->save();
                $draft->fill(['organization_id' => $organization->id, 'completed_at' => now(), 'current_step' => 'complete']);
            }
            $draft->save();
            return $draft;
        });
    }
}
