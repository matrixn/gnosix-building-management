<?php

namespace Tests\Feature;

use App\Evidence\Engine;
use App\Evidence\Extractor;
use App\Evidence\ProviderExtractor;
use App\Evidence\Providers;
use App\Evidence\Settings;
use App\Jobs\ExtractEvidence;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceProvidersTest extends TestCase
{
    use RefreshDatabase;

    private function setupMemory(): array
    {
        Http::preventStrayRequests();
        Storage::fake('evidence');
        Queue::fake();
        $u = User::factory()->create(['locale' => 'en', 'onboarding_completed_at' => now()]);
        $o = Organization::create(['name' => 'AI tenant']);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $o->memberships()->create(['user_id' => $u->id, 'role' => 'owner']);
        $p = $o->properties()->create(['name' => 'AI property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b = $p->buildings()->create(['name' => 'AI building', 'state' => 'active']);
        $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_locale' => 'en']);
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('source.png', 20, 20)], 'invoice')[0]['version'];

        return [$u, $o, $p, $b, $v];
    }

    private function configure(string $provider, string $model): void
    {
        PlatformSetting::updateOrCreate(['key' => 'evidence'], ['value' => ['enabled' => true, 'provider' => $provider, 'providers' => [$provider => ['model' => $model, 'api_key' => 'key-'.$provider, 'free_only' => true]]]]);
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'AI admin', 'email' => 'ai-admin@example.test', 'password' => 'long-test-password']);
    }

    public function test_provider_adapters_use_distinct_credentials_and_structured_non_tool_requests(): void
    {
        [$u,$o,$p,$b,$v] = $this->setupMemory();
        $json = json_encode(['document_type' => 'invoice', 'facts' => []]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $json]]]]]]),
            'openrouter.ai/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => $json]]]]),
            '127.0.0.1:11434/api/show' => Http::response(['model_info' => ['general.architecture' => 'vision'], 'capabilities' => ['completion', 'vision']]),
            '127.0.0.1:11434/api/chat' => Http::response(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => $json]]),
        ]);
        foreach (['gemini' => 'gemini-test', 'openrouter' => 'vision/test:free', 'ollama' => 'vision:local'] as $provider => $model) {
            $this->configure($provider, $model);
            $this->assertSame([], $this->app->make(Extractor::class)->extract($v)['facts']);
        }
        $this->assertInstanceOf(ProviderExtractor::class, $this->app->make(Extractor::class));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage.googleapis.com') && $r->hasHeader('x-goog-api-key', 'key-gemini') && ! $r->hasHeader('Authorization') && isset($r['generationConfig']['responseJsonSchema']) && $r['contents'][0]['parts'][1]['inlineData']['mimeType'] === 'image/png');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'openrouter.ai') && $r->hasHeader('Authorization', 'Bearer key-openrouter') && $r['model'] === 'vision/test:free' && $r['provider']['allow_fallbacks'] === false && $r['provider']['require_parameters'] === true && $r['response_format']['json_schema']['strict'] === true && ! isset($r['tools']));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/chat') && ! $r->hasHeader('Authorization') && isset($r['format']['properties']) && isset($r['messages'][1]['images'][0]) && ! isset($r['tools']));
        Http::assertSentCount(4);
    }

    public function test_provider_settings_preserve_keys_per_source_and_adopt_legacy_openai(): void
    {
        $this->setupMemory();
        $admin = $this->admin();
        PlatformSetting::create(['key' => 'evidence', 'value' => ['enabled' => true, 'provider' => 'openai', 'model' => 'legacy-model', 'api_key' => 'legacy-openai']]);
        $s = app(Settings::class);
        $this->assertSame('legacy-openai', $s->all()['api_key']);
        $base = ['enabled' => true, 'max_file_mb' => 8, 'building_quota_mb' => 512];
        $s->save($base + ['provider' => 'gemini', 'model' => 'gemini-test', 'api_key' => 'gemini-secret'], $admin->id);
        $this->assertSame('gemini-secret', $s->all()['api_key']);
        $this->assertSame('legacy-openai', $s->forProvider('openai')['api_key']);
        $s->save($base + ['provider' => 'openrouter', 'model' => 'openrouter/free', 'api_key' => 'router-secret'], $admin->id);
        $s->save($base + ['provider' => 'gemini', 'model' => 'gemini-test', 'api_key' => ''], $admin->id);
        $this->assertSame('gemini-secret', $s->all()['api_key']);
        $s->save($base + ['provider' => 'gemini', 'model' => 'gemini-test', 'clear_key' => true], $admin->id);
        $this->assertFalse($s->available());
        $this->assertSame('router-secret', $s->forProvider('openrouter')['api_key']);
        $raw = DB::table('platform_settings')->where('key', 'evidence')->value('value');
        $this->assertStringNotContainsString('router-secret', $raw);
        Http::assertNothingSent();
    }

    public function test_free_only_and_local_model_rules_cannot_be_bypassed(): void
    {
        $this->setupMemory();
        foreach (['vendor/paid', 'vendor/model:free/other', 'https://evil.test/model'] as $model) {
            $this->assertFalse(Providers::validModel('openrouter', $model));
        }
        $this->assertTrue(Providers::validModel('openrouter', 'openrouter/free'));
        $this->assertTrue(Providers::validModel('openrouter', 'vendor/vision:free'));
        $this->assertTrue(Providers::validModel('openrouter', 'vendor/paid', false));
        $this->assertFalse(Providers::validModel('ollama', 'vision:cloud'));
        $this->assertFalse(Providers::validModel('ollama', 'vision-cloud'));
        $this->assertFalse(Providers::validModel('gemini', '../../evil'));
        $this->configure('openrouter', 'vendor/paid');
        $this->assertFalse(app(Settings::class)->available());
        $this->configure('ollama', 'vision:local');
        $this->assertTrue(app(Settings::class)->available());
    }

    public function test_pdf_is_supported_by_gemini_but_denied_before_queue_for_image_only_providers(): void
    {
        [$u,$o,$p,$b] = $this->setupMemory();
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->createWithContent('source.pdf', "%PDF-1.4\n plain text\n%%EOF")], 'technical')[0]['version'];
        foreach (['openrouter' => 'openrouter/free', 'ollama' => 'vision:local'] as $provider => $model) {
            $this->configure($provider, $model);
            $this->post(route('app.evidence.extract', [$o, $p, $b->uuid, $v->id]), ['consent' => 1])->assertSessionHasErrors('provider');
            $this->get(route('app.evidence.show', [$o, $p, $b->uuid, 'version' => $v->id]))->assertOk()->assertSee(__('evidence.unsupported_format'))->assertDontSee('name="consent"', false);
        }
        $this->assertDatabaseCount('evidence_extractions', 0);
        Http::assertNothingSent();
        $this->configure('gemini', 'gemini-test');
        $this->post(route('app.evidence.extract', [$o, $p, $b->uuid, $v->id]), ['consent' => 1])->assertRedirect();
        $this->assertDatabaseCount('evidence_extractions', 1);
    }

    public function test_provider_switch_invalidates_queued_consent_without_transmitting(): void
    {
        [$u,$o,$p,$b,$v] = $this->setupMemory();
        $this->configure('gemini', 'gemini-test');
        $job = app(Engine::class)->extract($u, $b, $v, true);
        $this->configure('openrouter', 'openrouter/free');
        (new ExtractEvidence($job->id))->handle(app(Extractor::class), app(Engine::class));
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertSame('provider_changed', $job->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_quota_errors_do_not_fallback_or_fake_success(): void
    {
        [$u,$o,$p,$b,$v] = $this->setupMemory();
        $this->configure('gemini', 'gemini-test');
        $job = app(Engine::class)->extract($u, $b, $v, true);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429)]);
        try {
            (new ExtractEvidence($job->id))->handle(app(Extractor::class), app(Engine::class));
            $this->fail('Quota failure swallowed');
        } catch (\RuntimeException) {
        }
        $this->assertSame('retrying', $job->fresh()->status);
        $this->assertDatabaseCount('evidence_assertions', 0);
        Http::assertSentCount(1);
    }

    public function test_admin_provider_form_validates_models_and_never_renders_other_keys(): void
    {
        $this->setupMemory();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_confirmed_at' => time()]);
        $data = ['enabled' => 1, 'provider' => 'openrouter', 'model' => 'vendor/paid', 'free_only' => 1, 'api_key' => 'do-not-flash', 'max_file_mb' => 8, 'building_quota_mb' => 512];
        $this->put('/admin/settings/evidence', $data)->assertSessionHasErrors('model');
        $this->assertNull(session()->getOldInput('api_key'));
        $data['model'] = 'vendor/vision:free';
        $this->put('/admin/settings/evidence', $data)->assertRedirect();
        $this->assertSame('openrouter', app(Settings::class)->all()['provider']);
        $this->get('/admin/settings?tab=evidence&ai_provider=gemini')->assertOk()->assertSee('Google Gemini')->assertDontSee('do-not-flash');
        Http::assertNothingSent();
    }

    public function test_ollama_rejects_remote_alias_before_sending_the_document(): void
    {
        [$u,$o,$p,$b,$v] = $this->setupMemory();
        $this->configure('ollama', 'innocent-alias');
        Http::fake(['127.0.0.1:11434/api/show' => Http::response(['remote_host' => 'https://ollama.com', 'capabilities' => ['vision']])]);
        try {
            app(Extractor::class)->extract($v);
            $this->fail('Cloud alias accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame('provider_model_incompatible', $e->getMessage());
        }Http::assertSentCount(1);
    }

    public function test_invalid_model_shapes_fail_validation_without_server_errors(): void
    {
        $this->setupMemory();
        $admin = $this->admin();
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_confirmed_at' => time()]);
        $this->put('/admin/settings/evidence', ['provider' => 'gemini', 'enabled' => 1, 'model' => ['invalid'], 'max_file_mb' => 8, 'building_quota_mb' => 512])->assertSessionHasErrors('model');
        Http::assertNothingSent();
    }
}
