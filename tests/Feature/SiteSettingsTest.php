<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SiteSettings::class)->save(SiteSettings::defaults());
    }

    private function loginAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::create(['name' => 'Settings Demo', 'email' => 'platform@example.test', 'password' => 'Synthetic-password-123']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_version' => $admin->auth_version, 'platform_admin_confirmed_at' => time()]);
        return $admin;
    }

    private function settings(array $overrides = []): array
    {
        return array_replace(['canonical_url' => 'https://public.example.test/', 'contact_recipient' => 'contact@example.test', 'contact_enabled' => '1', 'indexable' => '1'], $overrides);
    }

    public function test_settings_require_platform_admin_and_recent_authentication(): void
    {
        $this->put('/admin/settings/gnosix', $this->settings())->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create(), 'web')->put('/admin/settings/gnosix', $this->settings())->assertRedirect(route('admin.login'));
        $this->loginAdmin();
        $this->withSession(['platform_admin_confirmed_at' => time() - 601])->put('/admin/settings/gnosix', $this->settings())->assertRedirect(route('admin.settings', ['tab' => 'security']));
        $this->assertFalse(SiteSettings::value('indexable'));
    }

    public function test_saved_values_immediately_control_both_languages_robots_and_sitemap(): void
    {
        $admin = $this->loginAdmin();
        $this->get('/admin/settings?tab=gnosix')->assertOk()->assertSee('Salvează setările GNOSIX');
        $this->put('/admin/settings/gnosix', $this->settings())->assertSessionHasNoErrors();
        $this->assertSame('https://public.example.test', SiteSettings::value('canonical_url'));
        $this->assertSame($admin->id, PlatformSetting::find('gnosix')->updated_by);
        $this->get('/')->assertSee('href="https://public.example.test/"', false)->assertSee('index,follow,max-image-preview:large');
        $this->get('/en')->assertSee('href="https://public.example.test/en"', false)->assertSee('https://public.example.test/images/gnosix-social-en.png');
        $this->get('/robots.txt')->assertSee('Allow: /')->assertSee('https://public.example.test/sitemap.xml');
        $this->get('/sitemap.xml')->assertSee('https://public.example.test/en/privacy');
        $this->get('/admin/settings')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->put('/admin/settings/gnosix', $this->settings(['indexable' => '0', 'contact_enabled' => '0']))->assertSessionHasNoErrors();
        $this->get('/en')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('class="contact-fields" disabled', false);
        $this->get('/robots.txt')->assertSee('Disallow: /');
    }

    public function test_contact_uses_the_database_recipient_and_can_be_disabled(): void
    {
        Mail::fake();
        app(SiteSettings::class)->save(['contact_enabled' => true, 'contact_recipient' => 'new-recipient@example.test']);
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.scheme' => 'smtps', 'mail.from.address' => 'sender@example.test']);
        $data = ['name' => 'Contact Demo', 'email' => 'visitor@example.test', 'phone' => '0722 123 456', 'subject' => 'pilot', 'message' => 'A synthetic contact enquiry.', 'consent' => '1'];
        $this->post('/en/contact', $data)->assertSessionHas('contact_sent', true);
        Mail::assertSent(ContactMessage::class, fn ($mail) => $mail->hasTo('new-recipient@example.test'));
        app(SiteSettings::class)->save(['contact_enabled' => false]);
        $this->post('/contact', $data)->assertSessionHasErrors('delivery');
        Mail::assertSentCount(1);
    }

    public static function invalidSettings(): array
    {
        return [
            ['canonical_url', 'javascript:alert(1)'], ['canonical_url', 'https://user:password@example.test'],
            ['canonical_url', 'https://example.test/en'], ['canonical_url', 'https://example.test?token=value'],
            ['canonical_url', 'https://example.test/#fragment'], ['contact_recipient', 'not-an-email'],
            ['contact_enabled', 'arbitrary'], ['indexable', 'arbitrary'],
        ];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_cannot_replace_saved_values(string $field, string $value): void
    {
        $this->loginAdmin();
        $this->put('/admin/settings/gnosix', $this->settings([$field => $value]))->assertSessionHasErrors($field);
        $this->assertSame(SiteSettings::defaults(), app(SiteSettings::class)->all());
    }

    public function test_production_requires_https_for_canonical_url(): void
    {
        $this->loginAdmin();
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->put('/admin/settings/gnosix', $this->settings(['canonical_url' => 'http://example.test']))->assertSessionHasErrors('canonical_url');
    }

    public function test_legacy_config_cannot_override_administration_and_requests_do_not_keep_stale_values(): void
    {
        config(['gnosix.indexable' => true, 'gnosix.contact_enabled' => true, 'gnosix.canonical_url' => 'https://legacy.example.test']);
        $this->get('/robots.txt')->assertSee('Disallow: /');
        $this->get('/')->assertSee('https://gnosix.zion3d.ro/')->assertDontSee('legacy.example.test');
        $row = PlatformSetting::find('gnosix'); $values = $row->value; $values['indexable'] = true;
        $row->update(['value' => $values]);
        $this->get('/robots.txt')->assertSee('Allow: /');
    }

    public function test_migration_imports_legacy_environment_once_without_overwriting_admin_changes(): void
    {
        $old = [];
        $values = ['GNOSIX_CANONICAL_URL' => 'https://imported.example.test/', 'GNOSIX_INDEXABLE' => 'false', 'GNOSIX_CONTACT_ENABLED' => 'true', 'GNOSIX_CONTACT_RECIPIENT' => 'imported@example.test'];
        try {
            foreach ($values as $key => $value) { $old[$key] = getenv($key); putenv($key.'='.$value); }
            PlatformSetting::where('key', 'gnosix')->delete();
            $migration = require database_path('migrations/2026_11_15_120000_move_gnosix_settings_to_administration.php');
            $migration->up();
            app(SiteSettings::class)->reset();
            $this->assertSame('https://imported.example.test', SiteSettings::value('canonical_url'));
            $this->assertSame('imported@example.test', SiteSettings::value('contact_recipient'));
            $this->assertTrue(SiteSettings::value('contact_enabled'));
            $this->assertFalse(SiteSettings::value('indexable'));
            app(SiteSettings::class)->save(['indexable' => true, 'contact_recipient' => 'administrator@example.test']);
            $migration->up();
            app(SiteSettings::class)->reset();
            $this->assertSame('administrator@example.test', SiteSettings::value('contact_recipient'));
            $this->assertTrue(SiteSettings::value('indexable'));
        } finally {
            foreach ($old as $key => $value) $value === false ? putenv($key) : putenv($key.'='.$value);
        }
    }
}
