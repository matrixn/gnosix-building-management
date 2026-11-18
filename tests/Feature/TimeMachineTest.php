<?php

namespace Tests\Feature;

use App\Assets\Registry;
use App\Dna\Relations;
use App\Evidence\Engine;
use App\Maintenance\Workflow;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\BuildingRevision;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\BuildingDeclarationStudio;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TimeMachineTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Property $property;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuilding();
    }

    private function seedBuilding(): void
    {
        $this->travelTo(CarbonImmutable::parse('2023-01-05 12:00:00', 'UTC'));
        $this->owner = User::factory()->create(['locale' => 'en', 'profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $this->organization = Organization::create(['name' => 'Temporal QA']);
        $this->organization->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $this->organization->memberships()->create(['user_id' => $this->owner->id, 'role' => 'owner']);
        $this->property = $this->organization->properties()->create(['name' => 'Retreat', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $this->building = $this->property->buildings()->create(['name' => 'North Pavilion', 'state' => 'active']);
        $this->login($this->owner);
        Storage::fake('evidence');
    }

    private function login(User $user): void
    {
        $this->actingAs($user, 'web')->withSession(['customer_auth_version' => $user->auth_version, 'customer_locale' => 'en']);
    }

    private function url(array $input = [], bool $pdf = false): string
    {
        return route($pdf ? 'app.time-machine.export' : 'app.time-machine.show', [$this->organization, $this->property, $this->building->uuid] + $input);
    }

    private function asset(string $name, array $fields = [], $asset = null)
    {
        return app(Registry::class)->save($this->owner, $this->property, [
            'name' => $name,
            'building_id' => $this->building->id,
            'asset_type_id' => AssetType::where('key', 'hvac')->value('id'),
            'status' => 'unknown',
            ...$fields,
        ], $asset);
    }

    public function test_absent_history_remains_unknown_and_pending_installation_is_marked(): void
    {
        $this->get(route('app.time-machine.index', $this->organization))->assertOk()->assertSee('North Pavilion');
        $this->get($this->url(['at' => '2022-01-01']))->assertOk()->assertSee('No documented records')->assertSee('Coverage is incomplete');
        $this->asset('Future boiler', ['installation_date' => '2023-02-10']);
        $records = $this->get($this->url())->assertOk()->viewData('state')['rows'];
        $this->assertSame('not_yet_installed', collect($records)->firstWhere('label', 'Future boiler')['state']);
        $this->travelTo(CarbonImmutable::parse('2024-06-02 12:00:00'));
        $this->asset('Registered late', ['installation_date' => '2022-09-09']);
        $view = $this->get($this->url(['at' => '2023-01-06']))->assertOk()->viewData('state');
        $late = collect($view['rows'])->firstWhere('label', 'Registered late');
        $this->assertSame('installation', $late['kind']);
        $this->assertTrue($late['later']);
        $view = $this->get($this->url(['at' => '2023-01-06', 'mode' => 'then']))->assertOk()->viewData('state');
        $this->assertNull(collect($view['rows'])->firstWhere('label', 'Registered late'));
    }

    public function test_dates_reconstruct_move_replacement_and_room_reconfiguration(): void
    {
        $level = $this->building->floors()->create(['name' => 'Ground', 'position' => 0]);
        $room = $this->property->spaces()->create(['name' => 'Room Old', 'space_type' => 'room', 'building_id' => $this->building->id, 'floor_id' => $level->id, 'area_m2' => 12]);
        $old = $this->asset('Boiler A', ['space_id' => $room->id]);
        $otherBuilding = $this->property->buildings()->create(['name' => 'South', 'state' => 'active']);
        $this->travelTo(CarbonImmutable::parse('2024-04-15 12:00:00'));
        $room->update(['name' => 'Room New', 'area_m2' => 17]);
        $this->asset('Boiler A', ['revision' => $old->revision, 'building_id' => $otherBuilding->id], $old);
        $replacement = $this->asset('Boiler B', ['space_id' => $room->id, 'warranty_end' => '2027-01-01']);
        $view = $this->get($this->url(['at' => '2023-01-06', 'compare' => '2024-04-15']))->assertOk()
            ->assertSee('Room Old')->assertSee('Room New')->assertSee('Boiler A')->assertSee('Boiler B');
        $this->assertArrayNotHasKey('asset:'.$old->uuid, $view->viewData('other')['rows']);
        $this->assertSame('2027-01-01', $view->viewData('other')['rows']['asset:'.$replacement->uuid]['fields']['warranty_end']);
        $this->assertSame($level->uuid, $view->viewData('state')['rows']['space:'.$room->uuid]['fields']['floor']);
    }

    public function test_relationship_retirement_and_intervention_have_distinct_provenance(): void
    {
        $a = $this->asset('Boiler');
        $b = $this->asset('Pump');
        $relation = app(Relations::class)->create($this->owner, $this->property, ['source' => 'asset:'.$a->uuid, 'target' => 'asset:'.$b->uuid, 'type_key' => 'supplies', 'declaration' => 'Declared route', 'proposed' => false]);
        $work = WorkOrder::create(['organization_id' => $this->organization->id, 'property_id' => $this->property->id, 'building_id' => $this->building->id, 'title' => 'Replace valve', 'category' => 'hvac', 'priority' => 'normal', 'state' => 'in_progress']);
        app(Workflow::class)->audit($work, $this->owner, 'work_created');
        $this->travelTo(CarbonImmutable::parse('2023-02-10 12:00:00'));
        app(Relations::class)->review($this->owner, $relation, ['state' => 'superseded', 'revision' => $relation->revision, 'reason' => 'Replaced line']);
        $work->update(['work_performed' => 'Installed a replacement valve']);
        app(Workflow::class)->audit($work, $this->owner, 'performed');
        $view = $this->get($this->url(['at' => '2023-01-06', 'compare' => '2023-02-10']))->assertOk()->assertSee('Installed a replacement valve');
        $this->assertSame('declared', $view->viewData('state')['rows']['relation:'.$relation->uuid]['state']);
        $this->assertSame('superseded', $view->viewData('other')['rows']['relation:'.$relation->uuid]['state']);
    }

    public function test_evidence_effective_time_review_cutoff_conflicts_and_permissions(): void
    {
        $engine = app(Engine::class);
        $this->travelTo(CarbonImmutable::parse('2025-07-01 12:00:00'));
        $upload = $engine->uploads($this->owner, $this->building, [UploadedFile::fake()->createWithContent('late-plan.pdf', "%PDF-1.4\n%%EOF")], 'installation')[0]['version'];
        $fact = $engine->propose($this->owner, $this->building, ['semantic_key' => 'equipment.installation_date', 'subject_type' => 'equipment', 'subject_reference' => 'CT-22', 'value' => '2022-09-09', 'page' => 2], $upload);
        $this->travelTo(CarbonImmutable::parse('2025-07-02 12:00:00'));
        $engine->review($this->owner, $this->building, $fact->id, $fact->revision, 'confirm');
        $engine->propose($this->owner, $this->building, ['semantic_key' => 'equipment.installation_date', 'subject_type' => 'equipment', 'subject_reference' => 'CT-22', 'value' => '2022-10-15']);
        $this->get($this->url(['at' => '2023-01-06', 'mode' => 'then']))->assertOk()->assertDontSee('late-plan.pdf');
        $view = $this->get($this->url(['at' => '2023-01-06', 'known' => '2025-07-01T12:00:00']))->assertOk();
        $this->assertSame('proposed', $view->viewData('state')['rows']['assertion:'.$fact->id]['state']);
        $view = $this->get($this->url(['at' => '2023-01-06']))->assertOk()->assertSee('late-plan.pdf')->assertSee('Conflicting historical assertions');
        $row = $view->viewData('state')['rows']['assertion:'.$fact->id];
        $this->assertSame('2022-09-09', $row['effective']);
        $this->assertSame('human_reviewed', $row['support']);
        $this->assertStringContainsString('page=2', $row['url']);
        $reader = User::factory()->create(['profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $this->organization->memberships()->create(['user_id' => $reader->id, 'role' => 'reporter', 'access_scope' => 'all']);
        $this->login($reader);
        $this->get($this->url(['at' => '2023-01-06']))->assertOk()->assertDontSee('late-plan.pdf');
        $this->get($row['url'])->assertForbidden();
        $this->login(User::factory()->create(['profile_type' => 'manager', 'onboarding_completed_at' => now()]));
        $this->get($this->url())->assertForbidden();
        $this->get($this->url(['compare' => '2023-01-06'], true))->assertForbidden();
    }

    public function test_building_trash_restore_and_pdf_comparison(): void
    {
        $this->building->floors()->create(['name' => 'Ground', 'position' => 0]);
        $studio = app(BuildingDeclarationStudio::class);
        $this->travelTo(CarbonImmutable::parse('2023-02-01 12:00:00'));
        $studio->delete($this->owner, $this->property, $this->building, $this->building->fresh()->declaration_version);
        $view = $this->get($this->url())->assertOk()->assertSee('Moved to trash');
        $this->assertCount(1, $view->viewData('state')['rows']);
        $this->travelTo(CarbonImmutable::parse('2023-02-02 12:00:00'));
        $trashed = Building::withTrashed()->findOrFail($this->building->id);
        $studio->restore($this->owner, $this->property, $trashed, $trashed->declaration_version);
        $view = $this->get($this->url())->assertOk()->assertSee('Ground');
        $this->assertCount(2, $view->viewData('state')['rows']);
        $report = $this->get($this->url(['at' => '2023-02-01', 'compare' => '2023-02-02'], true))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $report->getContent());
    }

    public function test_thousands_of_revisions_are_ranked_with_bounded_queries_and_paginated(): void
    {
        DB::enableQueryLog();
        $this->get($this->url(['section' => 'structure', 'page' => 2]))->assertOk();
        $baselineQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
        $time = now()->toDateTimeString();
        for ($chunk = 0; $chunk < 8; $chunk++) {
            $events = [];
            for ($i = 0; $i < 400; $i++) {
                $events[] = [
                    'building_id' => $this->building->id,
                    'entity_type' => 'space',
                    'entity_reference' => sprintf('room-%03d', $i),
                    'action' => 'updated',
                    'source_kind' => 'manual',
                    'after_data' => json_encode(['building_id' => $this->building->id, 'name' => 'Room '.$i.' v'.$chunk]),
                    'created_at' => $time,
                ];
            }
            foreach (array_chunk($events, 100) as $batch) {
                DB::table('building_revisions')->insert($batch);
            }
        }
        DB::enableQueryLog();
        $view = $this->get($this->url(['section' => 'structure', 'page' => 2]))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($baselineQueries + 3, $queries);
        $this->assertFalse($view->viewData('state')['partial']);
        $this->assertSame(401, $view->viewData('results')->total());
        $this->assertCount(24, $view->viewData('results'));
        $this->assertSame('Room 50 v7', $view->viewData('state')['rows']['space:room-050']['label']);
        $this->assertSame(3201, BuildingRevision::where('building_id', $this->building->id)->count());
    }

    public function test_concurrent_review_is_visible_only_after_the_read_snapshot_ends(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The test uses two SQLite WAL connections.');
        }
        $default = DB::getDefaultConnection();
        $file = tempnam(sys_get_temp_dir(), 'gnosix-temporal-');
        $connection = array_replace(config('database.connections.sqlite'), ['database' => $file, 'journal_mode' => 'wal']);
        config(['database.connections.tm_read' => $connection, 'database.connections.tm_write' => $connection]);
        DB::setDefaultConnection('tm_read');
        try {
            Artisan::call('migrate', ['--force' => true, '--database' => 'tm_read']);
            DB::statement('PRAGMA journal_mode = WAL');
            $this->seedBuilding();
            $engine = app(Engine::class);
            $fact = $engine->propose($this->owner, $this->building, [
                'semantic_key' => 'equipment.installation_date',
                'subject_type' => 'equipment',
                'subject_reference' => 'WAL-1',
                'value' => '2022-01-01',
            ]);
            $written = false;
            DB::listen(function ($query) use ($engine, $fact, &$written) {
                if ($written || $query->connectionName !== 'tm_read' || ! str_contains($query->sql, 'building_revisions')) {
                    return;
                }
                $written = true;
                DB::setDefaultConnection('tm_write');
                try {
                    $engine->review($this->owner, $this->building, $fact->id, $fact->revision, 'confirm');
                } finally {
                    DB::setDefaultConnection('tm_read');
                }
            });
            $key = 'assertion:'.$fact->id;
            $first = $this->get($this->url(['at' => '2023-01-04', 'compare' => '2023-01-05']))->assertOk();
            $this->assertTrue($written);
            $this->assertSame('proposed', $first->viewData('state')['rows'][$key]['state']);
            $this->assertSame('proposed', $first->viewData('other')['rows'][$key]['state']);
            $second = $this->get($this->url())->assertOk();
            $this->assertSame('confirmed', $second->viewData('state')['rows'][$key]['state']);
        } finally {
            DB::setDefaultConnection($default);
            DB::purge('tm_read');
            DB::purge('tm_write');
            foreach ([$file, $file.'-wal', $file.'-shm'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }
}
