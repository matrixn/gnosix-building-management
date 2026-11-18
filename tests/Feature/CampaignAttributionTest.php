<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class CampaignAttributionTest extends TestCase
{
    use RefreshDatabase;

    private const CAMPAIGN = '/?utm_source=outreach&utm_medium=email&utm_campaign=uk_founding_partners_2026&utm_content=property';

    protected function setUp(): void
    {
        parent::setUp();
        app(SiteSettings::class)->save(['contact_enabled' => true, 'contact_recipient' => 'owner@example.test']);
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.scheme' => 'smtps', 'mail.from.address' => 'contact@example.test']);
        Notification::fake();
    }

    private function contact(): array
    {
        return ['name' => 'Test Visitor', 'email' => 'visitor@example.test', 'phone' => '+44 20 7946 0018', 'subject' => 'partnership', 'message' => 'Interested in the founding partner pilot.', 'consent' => '1'];
    }

    public function test_last_campaign_gets_contact_and_registration_with_one_converted_visit(): void
    {
        Mail::fake();
        $this->get('/?utm_campaign=initial')->assertOk();
        $this->get(self::CAMPAIGN.'&lead_email=visitor@example.test')->assertOk();
        $this->get(self::CAMPAIGN)->assertOk();
        $this->get('/en')->assertOk();

        $this->post('/en/contact?utm_campaign=spoofed', $this->contact())->assertSessionHas('contact_sent', true);
        $this->post('/en/contact', $this->contact())->assertSessionHas('contact_sent', true);
        $this->post('/app/register', [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.test',
            'password' => 'A-test-password-2026!',
            'password_confirmation' => 'A-test-password-2026!',
            'privacy' => '1',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('campaign_links', 2);
        $this->assertDatabaseHas('campaign_links', ['id' => 1, 'visits' => 1, 'converted_visits' => 0]);
        $this->assertDatabaseHas('campaign_links', ['id' => 2, 'visits' => 1, 'contacts' => 1, 'registrations' => 1, 'converted_visits' => 1]);
        $this->assertStringNotContainsString('visitor', DB::table('campaign_links')->where('id', 2)->value('tags'));
    }

    public function test_failed_actions_and_expired_visits_do_not_convert_and_cleanup_keeps_totals(): void
    {
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP test failure'));
        $this->get(self::CAMPAIGN)->assertOk();
        $this->postJson('/contact', [])->assertUnprocessable();
        $this->post('/contact', $this->contact())->assertSessionHasErrors('delivery');
        $this->postJson('/app/register', [])->assertUnprocessable();
        $this->assertDatabaseHas('campaign_links', ['visits' => 1, 'converted_visits' => 0]);

        $this->travel(2)->hours();
        $this->artisan('gnosix:campaign-cleanup')->assertSuccessful();
        $this->assertDatabaseCount('campaign_visits', 0);
        $this->assertDatabaseHas('campaign_links', ['visits' => 1, 'converted_visits' => 0]);

        $this->get(self::CAMPAIGN)->assertOk();
        $this->assertDatabaseHas('campaign_links', ['visits' => 2, 'converted_visits' => 0]);
    }

    public function test_invalid_tags_prefetch_and_privacy_signals_are_ignored(): void
    {
        Mail::fake();
        $this->get('/?utm_campaign[]=other')->assertOk();
        $this->get('/?utm_campaign=visitor%40example.test')->assertOk();
        $this->get('/?utm_source=outreach')->assertOk();
        $this->withHeaders(['Sec-Purpose' => 'prefetch'])->get(self::CAMPAIGN)->assertOk();
        $this->flushHeaders()->withHeaders(['Sec-GPC' => '1'])->get(self::CAMPAIGN)->assertOk();
        $this->assertDatabaseMissing('campaign_links', ['is_tagged' => true]);
        $this->assertDatabaseHas('campaign_links', ['is_tagged' => false, 'visits' => 1]);

        $this->flushHeaders()->get(self::CAMPAIGN)->assertOk();
        $this->withHeaders(['DNT' => '1'])->post('/contact', $this->contact())->assertSessionHas('contact_sent', true);
        $this->assertDatabaseHas('campaign_links', ['visits' => 1, 'converted_visits' => 0]);
        $this->assertDatabaseCount('campaign_visits', 2);
    }

    public function test_only_platform_admin_can_see_searchable_links_and_their_conversion_rate(): void
    {
        Mail::fake();
        $this->get(self::CAMPAIGN)->assertOk();
        $this->post('/contact', $this->contact())->assertSessionHas('contact_sent', true);
        $this->get('/en?utm_campaign=unrelated')->assertOk();
        $this->get('/admin/campaigns')->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create(), 'web')->getJson('/admin/campaigns')->assertUnauthorized();

        $admin = PlatformAdmin::create(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password' => 'test-password']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_version' => $admin->auth_version]);
        $this->get('/admin/campaigns?q=founding_partners&sort=conversions')->assertOk()
            ->assertSee('utm_source=outreach')
            ->assertSee('utm_content=property')
            ->assertSee('100,0%')
            ->assertDontSee('unrelated');
        $this->get('/admin/campaigns?q=foundingXpartners')->assertOk()->assertSee('Niciun link');
    }

    public function test_untagged_entries_show_referrer_counts_without_storing_referrer_paths(): void
    {
        Mail::fake();
        $this->get('/?lead=visitor@example.test')->assertOk();
        $this->withHeaders(['Referer' => 'https://gnosix.zion3d.ro/'])->get('/en')->assertOk();
        $this->assertDatabaseCount('campaign_links', 1);

        $this->withHeaders(['Referer' => 'https://www.Google.com/private/path?email=visitor@example.test'])->get('/en')->assertOk();
        $this->get('/en')->assertOk();
        $this->flushHeaders()->post('/en/contact', $this->contact())->assertSessionHas('contact_sent', true);

        $this->assertDatabaseHas('campaign_links', ['path' => '/', 'tags' => '[]', 'visits' => 1, 'converted_visits' => 0]);
        $row = DB::table('campaign_links')->where('path', '/en')->first();
        $this->assertFalse((bool) $row->is_tagged);
        $this->assertSame(1, (int) $row->visits);
        $this->assertSame(1, (int) $row->converted_visits);
        $this->assertSame(['google.com'], DB::table('campaign_link_referrers')->where('campaign_link_id', $row->id)->pluck('host')->all());

        $admin = PlatformAdmin::create(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password' => 'test-password']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_version' => $admin->auth_version]);
        $this->get('/admin/campaigns?kind=untagged&q=google.com')->assertOk()->assertSee('google.com')->assertSee('100,0%')
            ->assertDontSee('/private/path')->assertDontSee('visitor@example.test')->assertDontSee('Directă / necunoscută');
    }

    public function test_referrer_breakdown_preserves_existing_campaign_and_its_conversion_priority(): void
    {
        Mail::fake();
        $this->get(self::CAMPAIGN)->assertOk();
        // Simulate totals accumulated before referrers were measured.
        DB::table('campaign_link_referrers')->delete();
        $this->travel(2)->hours();
        $this->withHeaders(['Referer' => 'https://linkedin.com/feed/'])->get(self::CAMPAIGN)->assertOk();
        $this->withHeaders(['Referer' => 'https://google.com/search'])->get('/en')->assertOk();
        $this->flushHeaders()->post('/en/contact', $this->contact())->assertSessionHas('contact_sent', true);

        $this->assertDatabaseHas('campaign_links', ['is_tagged' => true, 'visits' => 2, 'contacts' => 1, 'converted_visits' => 1]);
        $this->assertDatabaseHas('campaign_links', ['is_tagged' => false, 'visits' => 1, 'contacts' => 0, 'converted_visits' => 0]);
        $admin = PlatformAdmin::create(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password' => 'test-password']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_version' => $admin->auth_version]);
        $this->get('/admin/campaigns?kind=tagged')->assertOk()->assertSee('linkedin.com')->assertSee('Vizite anterioare, fără măsurarea provenienței')
            ->assertSee('50,0%')->assertDontSee('google.com/search');
    }
}
