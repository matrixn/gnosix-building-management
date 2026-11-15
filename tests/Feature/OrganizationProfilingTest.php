<?php

namespace Tests\Feature;

use App\Models\OnboardingSession;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\CapabilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationProfilingTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['locale' => 'en', 'onboarding_completed_at' => now(), 'profile_type' => 'manager'], $attributes));
    }

    private function login(User $user): static
    {
        return $this->actingAs($user, 'web')->withSession(['customer_auth_version' => $user->auth_version, 'customer_locale' => $user->preferredLocale()]);
    }

    private function organization(User $user, string $role = 'owner', string $profile = 'hospitality'): Organization
    {
        $organization = Organization::create(['name' => 'Workspace '.$user->id.' '.Organization::count()]);
        $organization->profile()->create(['profile_key' => $profile, 'organization_kind' => 'company', 'schema_version' => 1]);
        $organization->memberships()->create(['user_id' => $user->id, 'role' => $role]);
        return $organization;
    }

    private function draft(User $user): array
    {
        $this->login($user)->get('/app/organizations/create')->assertOk();
        return ['version' => OnboardingSession::where('user_id', $user->id)->value('version'), 'profile_key' => 'hospitality', 'organization_kind' => 'independent', 'name' => 'Meridian Hotel'];
    }

    public function test_new_customer_sees_all_profiles_and_only_hospitality_can_be_selected(): void
    {
        $user = $this->member(['onboarding_completed_at' => null, 'profile_type' => null]);
        $html = $this->login($user)->get('/app/onboarding')->assertOk()->assertSee('Hospitality')->assertSee('Construction &amp; Development', false)->assertSee('Property &amp; Facility Management', false)->assertSee('Coming soon')->getContent();
        $this->assertMatchesRegularExpression('/name="profile_key" value="construction" disabled/', $html);
        $this->assertMatchesRegularExpression('/name="profile_key" value="facility" disabled/', $html);
        $this->assertStringNotContainsString('name="building_type"', $html);
        $this->get('/app/organizations/create?lang=ro')->assertOk()->assertSee('Ce construim împreună?')->assertSee('Operator independent')->assertSee('Portofoliu / lanț hotelier');
    }

    public function test_hospitality_creation_is_atomic_and_sets_owner_profile_preference_and_onboarding(): void
    {
        $user = $this->member(['onboarding_completed_at' => null]);
        $data = $this->draft($user);
        $this->post('/app/organization-onboarding', $data + ['role' => 'manager', 'user_id' => 999])->assertRedirect(route('app.dashboard'));
        $organization = Organization::sole();
        $this->assertDatabaseHas('organization_profiles', ['organization_id' => $organization->id, 'profile_key' => 'hospitality', 'organization_kind' => 'independent']);
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'owner']);
        $this->assertDatabaseHas('user_preferences', ['user_id' => $user->id, 'active_organization_id' => $organization->id]);
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->assertSame('manager', $user->fresh()->profile_type);
        $this->assertNotNull(OnboardingSession::sole()->completed_at);
        $this->get('/app')->assertOk()->assertSee('Meridian Hotel');
    }

    public function test_draft_survives_refresh_and_has_optimistic_concurrency_protection(): void
    {
        $user = $this->member(); $data = $this->draft($user);
        $this->postJson('/app/organization-onboarding/draft', $data)->assertOk()->assertJson(['version' => $data['version'] + 1, 'step' => 'name']);
        $this->get('/app/organizations/create')->assertOk()->assertSee('Meridian Hotel')->assertSee('Your saved draft is restored.');
        $this->postJson('/app/organization-onboarding/draft', array_replace($data, ['name' => 'Stale overwrite']))->assertUnprocessable()->assertJsonValidationErrors('draft');
        $this->assertSame('Meridian Hotel', OnboardingSession::sole()->state['name']);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_draft_state_is_whitelisted_and_another_users_draft_is_never_updated(): void
    {
        $first = $this->member(); $second = $this->member();
        $firstData = $this->draft($first);
        $this->postJson('/app/organization-onboarding/draft', $firstData)->assertOk();
        $secondData = $this->draft($second);
        $this->postJson('/app/organization-onboarding/draft', $secondData + ['user_id' => $first->id, 'organization_id' => 123, 'role' => 'owner', 'capabilities' => ['evidence.conflicts']])->assertOk();
        $this->assertSame(['profile_key', 'organization_kind', 'name'], array_keys(OnboardingSession::where('user_id', $second->id)->first()->state));
        $this->assertSame($firstData['version'] + 1, OnboardingSession::where('user_id', $first->id)->value('version'));
    }

    public function test_duplicate_completion_is_idempotent_and_new_flow_can_create_a_second_organization(): void
    {
        $user = $this->member(); $data = $this->draft($user);
        $this->post('/app/organization-onboarding', $data)->assertRedirect();
        $this->post('/app/organization-onboarding', $data)->assertRedirect();
        $this->assertDatabaseCount('organizations', 1);
        $second = $this->draft($user);
        $this->post('/app/organization-onboarding', array_replace($second, ['name' => 'Second workspace', 'organization_kind' => 'portfolio']))->assertRedirect();
        $this->assertDatabaseCount('organizations', 2);
        $this->assertSame(2, $user->organizations()->count());
    }

    public function test_future_profiles_and_malformed_drafts_are_rejected_on_the_server(): void
    {
        $user = $this->member(); $data = $this->draft($user);
        foreach (['construction', 'facility', 'unknown'] as $profile) {
            $this->postJson('/app/organization-onboarding/draft', array_replace($data, ['profile_key' => $profile]))->assertUnprocessable()->assertJsonValidationErrors('profile_key');
            $this->post('/app/organization-onboarding', array_replace($data, ['profile_key' => $profile]))->assertSessionHasErrors('profile_key');
        }
        $this->postJson('/app/organization-onboarding/draft', array_replace($data, ['profile_key' => ['malformed']]))->assertUnprocessable();
        $this->post('/app/organization-onboarding', array_replace($data, ['organization_kind' => 'hotel']))->assertSessionHasErrors('organization_kind');
        $this->assertDatabaseCount('organizations', 0);
        $organization = $this->organization($user);
        foreach (['construction', 'facility'] as $profile) {
            $this->patch('/app/organizations/'.$organization->id, ['name' => $organization->name, 'profile_key' => $profile, 'organization_kind' => 'company'])->assertSessionHasErrors('profile_key');
        }
        $this->assertSame('hospitality', $organization->profile()->first()->profile_key);
    }

    public function test_organization_switching_and_lists_are_membership_scoped(): void
    {
        $user = $this->member(); $other = $this->member();
        $a = $this->organization($user); $b = $this->organization($user); $foreign = $this->organization($other);
        $this->login($user)->get('/app/organizations')->assertOk()->assertSee($a->name)->assertSee($b->name)->assertDontSee($foreign->name);
        $this->post('/app/organizations/'.$b->id.'/switch')->assertRedirect(route('app.dashboard'));
        $this->assertSame($b->id, UserPreference::where('user_id', $user->id)->value('active_organization_id'));
        $this->post('/app/organizations/'.$foreign->id.'/switch')->assertForbidden();
        $this->assertSame($b->id, UserPreference::where('user_id', $user->id)->value('active_organization_id'));
        $this->get('/app')->assertOk()->assertSee($b->name);
    }

    public function test_organization_policy_denies_foreign_resources_even_with_active_tenant_headers(): void
    {
        $user = $this->member(); $other = $this->member();
        $own = $this->organization($user); $foreign = $this->organization($other);
        $this->login($user)->withHeader('X-Organization-ID', $own->id)->get('/app/organizations/'.$foreign->id)->assertForbidden()->assertDontSee($foreign->name);
        $this->patch('/app/organizations/'.$foreign->id, ['name' => 'Hijacked', 'profile_key' => 'hospitality', 'organization_kind' => 'company'])->assertForbidden();
        $this->getJson('/app/organizations/'.$foreign->id.'/modules/hospitality.buildings')->assertForbidden();
        $this->assertNotSame('Hijacked', $foreign->fresh()->name);
    }

    public function test_capability_overrides_drive_navigation_and_direct_endpoint_authorization(): void
    {
        $user = $this->member(); $organization = $this->organization($user);
        $path = '/app/organizations/'.$organization->id;
        $this->login($user)->getJson($path.'/modules/hospitality.rooms')->assertOk()->assertJson(['allowed' => true, 'operational' => false]);
        $this->put($path.'/capabilities', ['capability' => 'hospitality.rooms', 'enabled' => '0'])->assertRedirect();
        $this->getJson($path.'/modules/hospitality.rooms')->assertForbidden();
        $this->get('/app')->assertOk()->assertDontSee($path.'/modules/hospitality.rooms', false);
        $this->put($path.'/capabilities', ['capability' => 'hospitality.rooms', 'enabled' => '1'])->assertRedirect();
        $this->getJson($path.'/modules/hospitality.rooms')->assertOk();
    }

    public function test_unreleased_unknown_and_wrong_profile_capabilities_cannot_be_forced_on(): void
    {
        $user = $this->member(); $organization = $this->organization($user); $path = '/app/organizations/'.$organization->id;
        $this->login($user);
        foreach (['evidence.conflicts', 'construction.projects', 'unknown.capability'] as $key) {
            $this->put($path.'/capabilities', ['capability' => $key, 'enabled' => '1'])->assertSessionHasErrors('capability');
            $organization->capabilityOverrides()->create(['capability' => $key, 'enabled' => true]);
            $this->getJson($path.'/modules/'.$key)->assertForbidden();
        }
        $organization->profile()->update(['profile_key' => 'construction']);
        $this->getJson($path.'/modules/evidence.upload')->assertForbidden();
    }

    public function test_member_read_access_cannot_escalate_to_profile_capability_or_membership_changes(): void
    {
        $user = $this->member(); $organization = $this->organization($user, 'member'); $path = '/app/organizations/'.$organization->id;
        $this->login($user)->get($path)->assertOk()->assertSee('Only owners and managers');
        $this->patch($path, ['name' => 'New', 'profile_key' => 'hospitality', 'organization_kind' => 'portfolio'])->assertForbidden();
        $this->put($path.'/capabilities', ['capability' => 'hospitality.rooms', 'enabled' => '0'])->assertForbidden();
        $this->post($path.'/members', ['email' => $user->email, 'role' => 'manager'])->assertForbidden();
        $this->assertDatabaseCount('organization_capabilities', 0);
    }

    public function test_owner_can_add_existing_verified_users_and_manage_non_owner_roles(): void
    {
        $owner = $this->member(); $second = $this->member(); $organization = $this->organization($owner); $path = '/app/organizations/'.$organization->id;
        $this->login($owner)->post($path.'/members', ['email' => $second->email, 'role' => 'member'])->assertRedirect();
        $membership = $organization->memberships()->where('user_id', $second->id)->firstOrFail();
        $this->patch($path.'/members/'.$membership->id, ['role' => 'manager'])->assertRedirect();
        $this->assertSame('manager', $membership->fresh()->role);
        $this->login($second)->patch($path, ['name' => 'Shared Hotel', 'profile_key' => 'hospitality', 'organization_kind' => 'portfolio'])->assertRedirect();
        $this->assertSame('Shared Hotel', $organization->fresh()->name);
        $this->post($path.'/members', ['email' => $owner->email, 'role' => 'member'])->assertForbidden();
    }

    public function test_owner_membership_cannot_be_removed_downgraded_or_assigned_from_input(): void
    {
        $owner = $this->member(); $second = $this->member(); $organization = $this->organization($owner); $path = '/app/organizations/'.$organization->id;
        $membership = $organization->memberships()->sole();
        $this->login($owner)->delete($path.'/members/'.$membership->id)->assertForbidden();
        $this->patch($path.'/members/'.$membership->id, ['role' => 'member'])->assertForbidden();
        $this->post($path.'/members', ['email' => $second->email, 'role' => 'owner'])->assertSessionHasErrors('role');
        $this->assertSame('owner', $membership->fresh()->role);
    }

    public function test_nested_membership_ids_are_scoped_and_revocation_invalidates_context(): void
    {
        $owner = $this->member(); $second = $this->member(); $other = $this->member();
        $organization = $this->organization($owner); $foreign = $this->organization($other); $path = '/app/organizations/'.$organization->id;
        $membership = $organization->memberships()->create(['user_id' => $second->id, 'role' => 'member']);
        $foreignMembership = $foreign->memberships()->sole();
        $this->login($owner)->delete($path.'/members/'.$foreignMembership->id)->assertNotFound();
        UserPreference::create(['user_id' => $second->id, 'active_organization_id' => $organization->id]);
        $this->delete($path.'/members/'.$membership->id)->assertRedirect();
        $this->login($second)->getJson($path.'/modules/hospitality.buildings')->assertForbidden();
        $this->assertNull(UserPreference::where('user_id', $second->id)->value('active_organization_id'));
        $this->assertDatabaseHas('organization_memberships', ['id' => $foreignMembership->id]);
    }

    public function test_unverified_and_platform_only_identities_cannot_access_organizations(): void
    {
        $this->get('/app/organizations')->assertRedirect(route('login'));
        $user = $this->member(['email_verified_at' => null]);
        $this->login($user)->get('/app/organizations/create')->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('onboarding_sessions', 0);
        Auth::guard('web')->logout();
        $admin = PlatformAdmin::create(['name' => 'Platform only', 'email' => 'platform-organization@example.test', 'password' => 'Platform-only-password-2026']);
        $this->actingAs($admin, 'platform_admin')->get('/app/organizations')->assertRedirect(route('login'));
    }

    public function test_forged_active_preference_falls_back_to_a_real_membership(): void
    {
        $user = $this->member(); $other = $this->member(); $own = $this->organization($user); $foreign = $this->organization($other);
        UserPreference::create(['user_id' => $user->id, 'active_organization_id' => $foreign->id]);
        $this->login($user)->get('/app')->assertOk()->assertSee($own->name)->assertDontSee($foreign->name);
        $this->assertSame($own->id, UserPreference::where('user_id', $user->id)->value('active_organization_id'));
    }

    public function test_future_profile_upgrade_preserves_building_types_evidence_memberships_and_opt_outs(): void
    {
        // Fixtures model future domain data; Task 002 intentionally adds no CRUD modules.
        Schema::create('buildings', function (Blueprint $table) {$table->id(); $table->foreignId('organization_id')->constrained(); $table->string('property_type');});
        Schema::create('evidence', function (Blueprint $table) {$table->id(); $table->foreignId('organization_id')->constrained(); $table->string('document');});
        $user = $this->member(); $organization = $this->organization($user); $path = '/app/organizations/'.$organization->id;
        DB::table('buildings')->insert(['organization_id' => $organization->id, 'property_type' => 'hotel']);
        DB::table('evidence')->insert(['organization_id' => $organization->id, 'document' => 'original-proof.pdf']);
        $organization->capabilityOverrides()->create(['capability' => 'hospitality.rooms', 'enabled' => false]);
        $definitions = config('profiles.capabilities');
        $definitions['construction.projects']['enabled'] = true;
        config(['profiles.profiles.construction.enabled' => true, 'profiles.capabilities' => $definitions]);
        $this->login($user)->patch($path, ['name' => $organization->name, 'profile_key' => 'construction', 'organization_kind' => 'company'])->assertRedirect();
        $this->getJson($path.'/modules/construction.projects')->assertOk();
        $this->assertDatabaseHas('buildings', ['organization_id' => $organization->id, 'property_type' => 'hotel']);
        $this->assertDatabaseHas('evidence', ['document' => 'original-proof.pdf']);
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'owner']);
        $this->patch($path, ['name' => $organization->name, 'profile_key' => 'hospitality', 'organization_kind' => 'portfolio'])->assertRedirect();
        $this->getJson($path.'/modules/hospitality.rooms')->assertForbidden();
        $this->assertDatabaseCount('buildings', 1); $this->assertDatabaseCount('evidence', 1);
    }

    public function test_organization_changes_remain_csrf_protected(): void
    {
        $user = $this->member(); $this->draft($user);
        $this->app->detectEnvironment(fn () => 'local');
        $this->post('/app/organization-onboarding/draft', ['version' => 1, 'profile_key' => 'hospitality'])->assertStatus(419);
        $this->app->detectEnvironment(fn () => 'testing');
        $this->assertSame(1, OnboardingSession::sole()->version);
    }

    public function test_tenant_context_does_not_leak_between_requests_or_trust_unknown_roles(): void
    {
        $first = $this->member(); $second = $this->member();
        $a = $this->organization($first); $b = $this->organization($second);
        $this->login($first)->get('/app')->assertOk()->assertSee($a->name)->assertDontSee($b->name);
        $this->assertNull(app(\App\Support\TenantContext::class)->current());
        $this->login($second)->get('/app')->assertOk()->assertSee($b->name)->assertDontSee($a->name);
        $b->memberships()->update(['role' => 'unknown-role']);
        $this->get('/app/organizations/'.$b->id)->assertForbidden();
        $this->getJson('/app/organizations/'.$b->id.'/modules/evidence.upload')->assertForbidden();
        $this->assertNull(UserPreference::where('user_id', $second->id)->value('active_organization_id'));
    }
}
