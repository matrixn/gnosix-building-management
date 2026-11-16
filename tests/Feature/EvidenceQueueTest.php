<?php

namespace Tests\Feature;

use App\Evidence\Engine;
use App\Evidence\Extractor;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Version;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceQueueTest extends TestCase
{
    use DatabaseMigrations;

    public function test_real_database_queue_serializes_and_processes_extraction_without_external_network(): void
    {
        Storage::fake('evidence');
        Http::preventStrayRequests();
        $u = User::factory()->create();
        $o = Organization::create(['name' => 'Queue tenant']);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $o->memberships()->create(['user_id' => $u->id, 'role' => 'owner']);
        $p = $o->properties()->create(['name' => 'Queue property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b = $p->buildings()->create(['name' => 'Queue building', 'state' => 'active']);
        PlatformSetting::create(['key' => 'evidence', 'value' => ['enabled' => true, 'provider' => 'openai', 'model' => 'test-model', 'api_key' => 'test-only-secret']]);
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('source.png', 20, 20)], 'invoice')[0]['version'];
        $fake = new class implements Extractor
        {
            public int $calls = 0;

            public function extract(Version $v): array
            {
                $this->calls++;

                return ['document_type' => 'invoice', 'facts' => [['semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => 'CT-01', 'value' => 'ABC123', 'quote' => 'Serial ABC123', 'page' => 1]]];
            }
        };
        $this->app->instance(Extractor::class, $fake);
        $request = app(Engine::class)->extract($u, $b, $v, true);
        $this->assertDatabaseCount('jobs', 1);
        $payload = DB::table('jobs')->sole();
        $this->assertSame('evidence', $payload->queue);
        $this->assertStringNotContainsString('test-only-secret', $payload->payload);
        Artisan::call('queue:work', ['connection' => 'evidence', '--queue' => 'evidence', '--once' => true, '--tries' => 3, '--timeout' => 120]);
        $this->assertSame('succeeded', $request->fresh()->status);
        $this->assertSame(1, $fake->calls);
        $this->assertSame('proposed', Assertion::sole()->status);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertNothingSent();
    }
}
