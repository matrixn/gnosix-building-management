<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\Notifications\CustomerResetPassword;
use App\Notifications\CustomerVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['password' => 'Customer-original-password-2026', 'locale' => 'en'], $attributes));
    }

    private function signedIn(User $user, ?int $version = null): static
    {
        return $this->actingAs($user, 'web')->withSession(['customer_auth_version' => $version ?? $user->auth_version]);
    }

    private function registration(array $attributes = []): array
    {
        return array_merge(['name' => 'Alex Hotelier', 'email' => 'ALEX@EXAMPLE.TEST', 'password' => 'Customer-new-password-2026', 'password_confirmation' => 'Customer-new-password-2026', 'privacy' => '1'], $attributes);
    }

    private function verification(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), ['id' => $user->id, 'hash' => sha1($user->email)], absolute: false);
    }

    public function test_registration_hashes_password_normalizes_email_and_restricts_unverified_users(): void
    {
        $this->get('/app/register?lang=en')->assertOk()->assertSee('One identity. A new beginning.');
        $this->post('/app/register', $this->registration(['auth_version' => 999, 'profile_type' => 'admin', 'email_verified_at' => now()]))->assertRedirect(route('verification.notice'));
        $user = User::sole();
        $this->assertSame('alex@example.test', $user->email);
        $this->assertTrue(Hash::check('Customer-new-password-2026', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->profile_type);
        $this->assertSame(1, $user->auth_version);
        $this->assertNotNull($user->first_signed_in_at);
        $this->assertSame('en', $user->locale);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertGuest('platform_admin');
        Notification::assertSentTo($user, CustomerVerifyEmail::class);
        foreach (['/app', '/app/account', '/app/onboarding'] as $path) $this->get($path)->assertRedirect(route('verification.notice'));
    }

    public function test_registration_validates_password_confirmation_privacy_and_never_flashes_secrets(): void
    {
        $this->post('/app/register', $this->registration(['password' => 'short', 'password_confirmation' => 'different', 'privacy' => '0']))->assertSessionHasErrors(['password', 'privacy']);
        $this->assertDatabaseCount('users', 0);
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());
        $this->post('/app/register', $this->registration(['email' => ['malformed']]))->assertSessionHasErrors('email');
        $this->post('/app/register', $this->registration(['password' => ['malformed']]))->assertSessionHasErrors('password');
        $this->postJson('/app/login', ['email' => ['malformed'], 'password' => ['malformed']])->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_registration_rejects_bcrypt_truncation_and_duplicate_emails(): void
    {
        $this->member(['email' => 'alex@example.test']);
        $this->post('/app/register', $this->registration())->assertSessionHasErrors('email');
        $long = str_repeat('ă', 40);
        $this->post('/app/register', $this->registration(['email' => 'new@example.test', 'password' => $long, 'password_confirmation' => $long]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_routing_first_sign_in_logout_and_existing_user_compatibility(): void
    {
        $user = $this->member();
        $this->post('/app/login', ['email' => $user->email, 'password' => 'Customer-original-password-2026'])->assertRedirect(route('app.onboarding'));
        $first = $user->fresh()->first_signed_in_at;
        $this->assertNotNull($first);
        $this->post('/app/logout')->assertRedirect();
        $this->assertGuest('web');
        $user->forceFill(['onboarding_completed_at' => now(), 'profile_type' => 'manager'])->save();
        $this->travel(5)->minutes();
        $this->post('/app/login', ['email' => strtoupper($user->email), 'password' => 'Customer-original-password-2026'])->assertRedirect(route('app.dashboard'));
        $this->assertTrue($first->equalTo($user->fresh()->first_signed_in_at));
        $this->assertTrue($user->fresh()->last_signed_in_at->greaterThan($first));
        $this->get('/app/login')->assertRedirect(route('app.dashboard'));
        $this->get('/app/logout')->assertMethodNotAllowed();
    }

    public function test_login_is_throttled_and_errors_do_not_reveal_account_existence(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/app/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/app/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])->assertTooManyRequests();
        $this->assertGuest('web');
    }

    public function test_customer_and_platform_admin_credentials_and_sessions_are_separate(): void
    {
        $admin = PlatformAdmin::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'Platform-only-password-2026']);
        $this->post('/app/login', ['email' => $admin->email, 'password' => 'Platform-only-password-2026'])->assertSessionHasErrors('email');
        $this->actingAs($admin, 'platform_admin')->get('/app/account')->assertRedirect(route('login'));
        $user = $this->member(['email' => $admin->email]);
        $response = $this->post('/app/login', ['email' => $user->email, 'password' => 'Customer-original-password-2026']);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNull(collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'gnosix_platform_session'));
    }

    public function test_verification_requires_owner_hash_signature_and_expiry(): void
    {
        $user = $this->member(['email_verified_at' => null]);
        $other = $this->member(['email_verified_at' => null]);
        $this->signedIn($user)->get($this->verification($other))->assertForbidden();
        $this->get('/app/verify-email/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->get($this->verification($user, -1))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->get($this->verification($user))->assertRedirect(route('app.onboarding'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get($this->verification($user))->assertRedirect(route('app.onboarding'));
    }

    public function test_verification_resend_is_throttled_and_is_customer_specific(): void
    {
        $user = $this->member(['email_verified_at' => null]);
        $this->signedIn($user)->post('/app/verification-notification')->assertRedirect(route('verification.notice'));
        Notification::assertSentToTimes($user, CustomerVerifyEmail::class, 1);
        $this->post('/app/verification-notification')->assertTooManyRequests();
    }

    public function test_password_reset_flow_has_hashed_expiring_single_use_tokens_and_no_auto_login(): void
    {
        $user = $this->member();
        $this->post('/app/forgot-password', ['email' => $user->email])->assertRedirect(route('password.request'));
        $token = null;
        Notification::assertSentTo($user, CustomerResetPassword::class, function ($notification) use (&$token) {$token = $notification->token; return true;});
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'Customer-reset-password-2026', 'password_confirmation' => 'Customer-reset-password-2026'];
        $this->post('/app/reset-password', $data)->assertRedirect();
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->assertGuest('web');
        $this->post('/app/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_reset_failure_and_missing_accounts_use_safe_feedback(): void
    {
        $user = $this->member();
        $this->post('/app/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $status = session('status');
        $this->post('/app/forgot-password', ['email' => 'missing@example.test'])->assertSessionHas('status', $status);
        $token = Password::broker('users')->createToken($user);
        $this->travel(61)->minutes();
        $this->post('/app/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Customer-reset-password-2026', 'password_confirmation' => 'Customer-reset-password-2026'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Customer-original-password-2026', $user->fresh()->password));
    }

    public function test_onboarding_only_accepts_informational_profiles_and_blocks_dashboard_until_complete(): void
    {
        $user = $this->member();
        $this->signedIn($user)->get('/app')->assertRedirect(route('app.onboarding'));
        $this->post('/app/onboarding', ['profile_type' => 'admin'])->assertSessionHasErrors('profile_type');
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->post('/app/onboarding', ['profile_type' => 'owner', 'tenant_id' => 123])->assertRedirect(route('app.dashboard'));
        $this->assertSame('owner', $user->fresh()->profile_type);
        $this->get('/app')->assertOk()->assertSee('Your account is ready.');
        $this->get('/app/onboarding')->assertRedirect(route('app.dashboard'));
        $this->get('/admin/settings')->assertRedirect(route('admin.login'));
    }

    public function test_profile_changes_are_self_only_and_language_is_persisted(): void
    {
        $user = $this->member(); $other = $this->member();
        $this->assertFalse(Gate::forUser($user)->allows('update', $other));
        $this->signedIn($user)->patch('/app/account', ['name' => 'New name', 'email' => $user->email, 'locale' => 'en', 'id' => $other->id, 'auth_version' => 500])->assertRedirect(route('app.account'));
        $this->assertSame('New name', $user->fresh()->name);
        $this->assertNotSame('New name', $other->fresh()->name);
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->get('/app/account')->assertOk()->assertHeader('Content-Language', 'en')->assertSee('Personal details');
        $this->get('/')->assertHeader('Content-Language', 'ro');
    }

    public function test_email_change_requires_password_reverification_and_revokes_other_sessions(): void
    {
        $user = $this->member();
        $data = ['name' => $user->name, 'email' => 'changed@example.test', 'locale' => 'en'];
        $this->signedIn($user)->patch('/app/account', $data)->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->patch('/app/account', $data + ['current_password' => 'Customer-original-password-2026'])->assertRedirect(route('verification.notice'));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(2, $user->fresh()->auth_version);
        Notification::assertSentTo($user, CustomerVerifyEmail::class);
        $this->get('/app/account')->assertRedirect(route('verification.notice'));
    }

    public function test_password_change_keeps_this_session_and_rejects_stale_sessions(): void
    {
        $user = $this->member();
        $this->signedIn($user)->put('/app/account/password', ['current_password' => 'wrong', 'password' => 'Customer-updated-password-2026', 'password_confirmation' => 'Customer-updated-password-2026'])->assertSessionHasErrors('current_password');
        $this->put('/app/account/password', ['current_password' => 'Customer-original-password-2026', 'password' => 'Customer-updated-password-2026', 'password_confirmation' => 'Customer-updated-password-2026'])->assertRedirect(route('app.account'));
        $this->assertTrue(Hash::check('Customer-updated-password-2026', $user->fresh()->password));
        $this->get('/app/account')->assertOk();
        $this->signedIn($user->fresh(), 1)->get('/app/account')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_other_device_logout_requires_password_and_increments_version(): void
    {
        $user = $this->member();
        $this->signedIn($user)->delete('/app/account/sessions', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->delete('/app/account/sessions', ['current_password' => 'Customer-original-password-2026'])->assertRedirect(route('app.account'));
        $this->assertSame(2, $user->fresh()->auth_version);
        $this->get('/app/account')->assertOk();
    }

    public function test_private_routes_do_not_leak_to_search_or_cache_and_guests_are_blocked(): void
    {
        foreach (['/app', '/app/account', '/app/onboarding'] as $path) $this->get($path)->assertRedirect(route('login'));
        $this->get('/app/login?lang=en')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('<html lang="en">', false);
        $this->get('/sitemap.xml')->assertDontSee('/app');
    }

    public function test_customer_post_routes_require_csrf(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->post('/app/login', ['email' => 'csrf@example.test', 'password' => 'irrelevant'])->assertStatus(419)->assertHeader('Cache-Control', 'no-store, private');
        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_notification_links_use_configured_host_not_the_request_host(): void
    {
        config(['app.url' => 'https://gnosix.zion3d.ro']);
        $user = $this->member();
        $this->withServerVariables(['HTTP_HOST' => 'attacker.example.test'])->get('/app/login');
        $verification = (new CustomerVerifyEmail)->toMail($user)->viewData['actionUrl'];
        $reset = (new CustomerResetPassword('synthetic-token'))->toMail($user)->viewData['actionUrl'];
        $this->assertStringStartsWith('https://gnosix.zion3d.ro/app/verify-email/', $verification);
        $this->assertStringStartsWith('https://gnosix.zion3d.ro/app/reset-password/', $reset);
    }

    public function test_notification_templates_render_with_the_mail_channel_reserved_message_object(): void
    {
        $user = $this->member();
        foreach ([new CustomerVerifyEmail, new CustomerResetPassword('synthetic-token')] as $notification) {
            $mail = $notification->toMail($user);
            $html = view($mail->view, array_merge($mail->viewData, ['message' => new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email)]))->render();
            $this->assertStringContainsString(e($mail->viewData['bodyCopy']), $html);
            $this->assertStringContainsString(e($mail->viewData['actionUrl']), $html);
        }
    }

    public function test_guest_can_resume_only_their_valid_verification_link_after_login(): void
    {
        $user = $this->member(['email_verified_at' => null]);
        $link = $this->verification($user);
        $this->get($link)->assertRedirect(route('login'));
        $response = $this->post('/app/login', ['email' => $user->email, 'password' => 'Customer-original-password-2026']);
        $this->assertStringContainsString($link, $response->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertRedirect(route('app.onboarding'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull(\App\Support\CustomerIdentity::intendedVerification('https://attacker.test/path', $user));
        $this->assertNull(\App\Support\CustomerIdentity::intendedVerification($this->verification($user, -1), $user));
    }

    public function test_reset_invalidates_an_existing_authenticated_session(): void
    {
        $user = $this->member();
        $this->signedIn($user);
        $token = Password::broker('users')->createToken($user);
        Auth::guard('web')->logout();
        $this->post('/app/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'New-server-reset-password-2026', 'password_confirmation' => 'New-server-reset-password-2026'])->assertRedirect();
        $this->signedIn($user->fresh(), 1)->get('/app/account')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_login_rotates_session_and_sets_http_only_host_scoped_cookie(): void
    {
        $user = $this->member();
        $this->get('/app/login');
        $before = session()->getId();
        $response = $this->post('/app/login', ['email' => $user->email, 'password' => 'Customer-original-password-2026']);
        $this->assertNotSame($before, session()->getId());
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
    }
}
