<?php

namespace Tests\Feature;

use App\Mail\PlatformMailManager;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Platform Demo', 'email' => 'platform@example.test', 'password' => 'Synthetic-test-password-123']);
    }

    private function loginAdmin(?PlatformAdmin $admin = null): PlatformAdmin
    {
        $admin ??= $this->administrator();
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_version' => $admin->auth_version, 'platform_admin_confirmed_at' => time()]);
        return $admin;
    }

    private function smtp(array $override = []): array
    {
        return array_replace(['host' => 'smtp.example.test', 'port' => 465, 'scheme' => 'smtps', 'username' => 'demo', 'smtp_password' => 'Synthetic-SMTP-secret', 'from_address' => 'sender@example.test', 'from_name' => 'GNOSIX'], $override);
    }

    public function test_customer_identity_does_not_grant_platform_administration(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'web')->get('/admin/settings')->assertRedirect(route('admin.login'));
        $this->put('/admin/settings/smtp', $this->smtp())->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('platform_settings', 0);
        $this->get('/admin/login')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_password_login_uses_only_platform_admin_provider_and_separate_cookie(): void
    {
        $admin = $this->administrator();
        $response = $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Synthetic-test-password-123']);
        $response->assertRedirect(route('admin.settings'))->assertCookie('gnosix_platform_session');
        $cookie = collect($response->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === 'gnosix_platform_session');
        $this->assertSame('/admin', $cookie->getPath());
        $this->assertAuthenticatedAs($admin, 'platform_admin');
        $this->assertGuest('web');
        $this->get('/admin/settings')->assertOk()->assertSee('Setările platformei.');
        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest('platform_admin');
    }

    public function test_customer_password_cannot_login_to_platform_even_with_same_email(): void
    {
        User::factory()->create(['email' => 'platform@example.test', 'password' => 'Customer-password-123']);
        $this->administrator();
        $this->post('/admin/login', ['email' => 'platform@example.test', 'password' => 'Customer-password-123'])->assertSessionHasErrors('email');
        $this->assertGuest('platform_admin');
    }

    public function test_login_is_throttled_and_admin_changes_require_recent_identity(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/admin/login', ['email' => 'none@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/admin/login', ['email' => 'none@example.test', 'password' => 'wrong'])->assertTooManyRequests();
        $this->loginAdmin();
        $this->withSession(['platform_admin_confirmed_at' => time() - 601])->put('/admin/settings/smtp', $this->smtp())->assertRedirect(route('admin.settings', ['tab' => 'security']));
        $this->assertDatabaseCount('platform_settings', 0);
    }

    public function test_smtp_is_encrypted_password_is_not_rendered_and_blank_preserves_it(): void
    {
        $this->loginAdmin();
        $this->put('/admin/settings/smtp', $this->smtp())->assertSessionHasNoErrors();
        $raw = DB::table('platform_settings')->where('key', 'smtp')->value('value');
        $this->assertStringNotContainsString('Synthetic-SMTP-secret', $raw);
        $this->assertSame('Synthetic-SMTP-secret', PlatformSetting::find('smtp')->value['password']);
        $this->get('/admin/settings?tab=smtp')->assertOk()->assertDontSee('Synthetic-SMTP-secret');
        $this->put('/admin/settings/smtp', $this->smtp(['smtp_password' => '']))->assertSessionHasNoErrors();
        $this->assertSame('Synthetic-SMTP-secret', PlatformSetting::find('smtp')->value['password']);
        $this->put('/admin/settings/smtp', $this->smtp(['smtp_password' => '', 'username' => '', 'clear_password' => '1']))->assertSessionHasNoErrors();
        $this->assertNull(PlatformSetting::find('smtp')->value['password']);
    }

    public function test_smtp_validation_never_flashes_password_and_test_only_targets_current_admin(): void
    {
        $admin = $this->loginAdmin();
        $this->put('/admin/settings/smtp', $this->smtp(['host' => 'https://invalid/path']))->assertSessionHasErrors('host');
        $this->assertArrayNotHasKey('smtp_password', session()->getOldInput());
        $this->assertDatabaseCount('platform_settings', 0);
        Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) use ($admin) {
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
            $callback($message);
            return $message->getSymfonyMessage()->getTo()[0]->getAddress() === $admin->email;
        });
        $this->post('/admin/settings/smtp/test', ['to' => 'attacker@example.test'])->assertSessionHasNoErrors();
    }

    public function test_all_named_mailers_use_saved_smtp_and_reload_changes(): void
    {
        PlatformSetting::create(['key' => 'smtp', 'value' => ['host' => 'smtp.one.test', 'port' => 465, 'scheme' => 'smtps', 'username' => 'demo', 'password' => 'test', 'from_address' => 'sender@example.test', 'from_name' => 'GNOSIX']]);
        $manager = app('mail.manager');
        $this->assertInstanceOf(PlatformMailManager::class, $manager);
        $transport = $manager->mailer('log')->getSymfonyTransport();
        $this->assertInstanceOf(\Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport::class, $transport);
        $this->assertStringContainsString('smtp.one.test', (string) $transport);
        $setting = PlatformSetting::find('smtp'); $data = $setting->value; $data['host'] = 'smtp.two.test'; $setting->update(['value' => $data]);
        $this->assertStringContainsString('smtp.two.test', (string) $manager->mailer('array')->getSymfonyTransport());
        $this->assertStringContainsString('smtp.two.test', (string) $manager->build(['transport' => 'log'])->getSymfonyTransport());
    }

    public function test_revoked_session_cannot_manage_settings(): void
    {
        $admin = $this->loginAdmin();
        $admin->increment('auth_version');
        $this->get('/admin/settings')->assertRedirect(route('admin.login'));
    }

    public function test_bootstrap_is_idempotent_and_explicit_reset_invalidates_existing_sessions(): void
    {
        $oldEmail = getenv('ADMIN_EMAIL'); $oldPassword = getenv('ADMIN_PASSWORD');
        try {
            putenv('ADMIN_EMAIL=bootstrap@example.test'); putenv('ADMIN_PASSWORD=Synthetic-bootstrap-123');
            $this->artisan('gnosix:admin')->assertSuccessful();
            $admin = PlatformAdmin::where('email', 'bootstrap@example.test')->firstOrFail();
            $this->assertTrue(Hash::check('Synthetic-bootstrap-123', $admin->password));
            $this->assertDatabaseCount('users', 0);
            putenv('ADMIN_PASSWORD=New-synthetic-password-456');
            $this->artisan('gnosix:admin')->assertSuccessful();
            $this->assertTrue(Hash::check('Synthetic-bootstrap-123', $admin->fresh()->password));
            $this->artisan('gnosix:admin', ['--reset-password' => true])->assertSuccessful();
            $this->assertTrue(Hash::check('New-synthetic-password-456', $admin->fresh()->password));
            $this->assertSame(2, $admin->fresh()->auth_version);
        } finally {
            $oldEmail === false ? putenv('ADMIN_EMAIL') : putenv('ADMIN_EMAIL='.$oldEmail);
            $oldPassword === false ? putenv('ADMIN_PASSWORD') : putenv('ADMIN_PASSWORD='.$oldPassword);
        }
    }
}
