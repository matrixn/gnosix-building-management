<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\BuildingDeclarationDraft;
use App\Models\Floor;
use App\Models\Organization;
use App\Models\Space;
use App\Models\User;
use App\Support\BuildingDeclarationSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuildingDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'owner'): array
    {
        $u = User::factory()->create(['locale' => 'en', 'profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $o = Organization::create(['name' => 'Atlas '.$u->id]);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $o->memberships()->create(['user_id' => $u->id, 'role' => $role]);
        $p = $o->properties()->create(['name' => 'Meridian', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);

        return [$u, $o, $p, '/app/organizations/'.$o->id.'/properties/'.$p->id];
    }

    private function login(User $u): static
    {
        return $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_locale' => 'en']);
    }

    private function draft(string $url, ?Building $b = null): BuildingDeclarationDraft
    {
        $this->get($b ? $url.'/declarations/'.$b->uuid.'/edit' : $url.'/building-studio')->assertRedirect();

        return BuildingDeclarationDraft::latest('created_at')->get()->first(fn ($d) => ! $d->completed_at && $d->property_id === (int) basename($url) && $d->building_id === $b?->id);
    }

    private function space(string $type = 'room', string $name = 'Room'): array
    {
        return ['key' => (string) Str::uuid(), 'name' => $name, 'space_type' => $type, 'number' => null, 'bed_count' => null, 'capacity' => null, 'area_m2' => null, 'beds' => []];
    }

    private function structure(string $preset = 'hotel', int $levels = 1, int $rooms = 1): array
    {
        $s = app(BuildingDeclarationSchema::class)->empty();
        $s['identity'] = array_replace($s['identity'], ['name' => 'Atlas House', 'classification' => $preset, 'state' => 'active']);
        for ($i = 0; $i < $levels; $i++) {
            $l = ['key' => (string) Str::uuid(), 'name' => $i ? 'Level '.$i : 'Ground', 'kind' => $i ? 'upper' : 'ground', 'spaces' => []];
            for ($j = 0; $j < $rooms; $j++) {
                $l['spaces'][] = $this->space('room', 'Room '.($i * 100 + $j + 1));
            }$s['levels'][] = $l;
        }

        return $s;
    }

    private function commit(string $url, BuildingDeclarationDraft $d, array $state, int $version = 1)
    {
        return $this->postJson($url.'/declaration-drafts/'.$d->id.'/complete', ['version' => $version, 'state' => $state]);
    }

    public static function cases(): array
    {
        return ['cabin' => ['cabin', 1, 0], 'guesthouse' => ['guesthouse', 1, 5], 'hotel' => ['hotel', 4, 10], 'mixed' => ['mixed', 2, 0], 'apartment' => ['apartment_building', 3, 2], 'house' => ['house', 1, 3], 'custom' => ['custom', 0, 0]];
    }

    #[DataProvider('cases')]
    public function test_declarations_preserve_identity_and_render_dashboard(string $preset, int $levels, int $rooms): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure($preset, $levels, $rooms);
        if ($preset === 'custom') {
            $s['identity']['custom_classification'] = 'Observatory';
        }if ($preset === 'mixed') {
            $s['levels'][0]['spaces'][] = $this->space('common', 'Gallery');
            $s['levels'][1]['spaces'][] = $this->space('apartment', 'Loft');
        }
        $this->get($url.'/declaration-drafts/'.$d->id)->assertOk()->assertSee('data-building-studio', false);
        $this->commit($url, $d, $s)->assertOk();
        $b = Building::sole();
        $this->assertTrue(Str::isUuid($b->uuid));
        $this->assertNotEmpty($b->reference);
        $this->assertNull($b->construction_year);
        $this->assertNull($b->latitude);
        $this->assertNull($b->country);
        $this->assertDatabaseCount('floors', $levels);
        $this->assertDatabaseCount('spaces', $preset === 'mixed' ? 2 : $levels * $rooms);
        $this->assertDatabaseCount('accommodation_units', 0);
        $this->get($url.'/declarations/'.$b->uuid)->assertOk()->assertSee('Atlas House')->assertSee('Unknown')->assertSee($b->uuid);
        $this->get($url.'/declarations/'.$b->uuid.'/export')->assertOk()->assertJsonPath('format', 'gnosix.building.v1')->assertJsonPath('building_uuid', $b->uuid);
        $this->commit($url, $d, $s)->assertOk();
        $this->assertDatabaseCount('buildings', 1);
    }

    public function test_hostel_has_physical_dormitories_beds_and_independent_bookable_units(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure('hostel', 1, 0);
        for ($i = 0; $i < 2; $i++) {
            $space = $this->space('dormitory', 'Dorm '.$i);
            $space['bed_count'] = 6;
            for ($j = 0; $j < 6; $j++) {
                $bed = ['key' => (string) Str::uuid(), 'label' => 'Bed '.$j];
                $space['beds'][] = $bed;
                $s['units'][] = ['key' => (string) Str::uuid(), 'name' => 'Dorm '.$i.' bed '.$j, 'unit_type' => 'bed', 'space_keys' => [$space['key']], 'bed_key' => $bed['key']];
            }$s['levels'][0]['spaces'][] = $space;
        }
        $this->commit($url, $d, $s)->assertOk();
        $this->assertDatabaseCount('spaces', 2);
        $this->assertDatabaseCount('space_beds', 12);
        $this->assertDatabaseCount('accommodation_units', 12);
        $this->assertDatabaseCount('accommodation_unit_space', 12);
    }

    public function test_suite_links_multiple_physical_rooms_and_whole_cabin_requires_no_fake_rooms(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure('hotel', 1, 2);
        $s['units'] = [['key' => (string) Str::uuid(), 'name' => 'Sky suite', 'unit_type' => 'suite', 'space_keys' => array_column($s['levels'][0]['spaces'], 'key'), 'bed_key' => null]];
        $this->commit($url, $d, $s)->assertOk();
        $this->assertDatabaseCount('accommodation_units', 1);
        $this->assertDatabaseCount('accommodation_unit_space', 2);
        $d = $this->draft($url);
        $s = $this->structure('cabin', 1, 0);
        $s['units'] = [['key' => (string) Str::uuid(), 'name' => 'Whole cabin', 'unit_type' => 'whole_building', 'space_keys' => [], 'bed_key' => null]];
        $this->commit($url, $d, $s)->assertOk();
        $this->assertDatabaseCount('buildings', 2);
        $this->assertDatabaseCount('spaces', 2);
    }

    public function test_resort_supports_three_distinct_buildings_on_one_property(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        for ($i = 0; $i < 3; $i++) {
            $d = $this->draft($url);
            $s = $this->structure('resort', 1, $i + 1);
            $s['identity']['name'] = 'Pavilion '.$i;
            $this->commit($url, $d, $s)->assertOk();
        }$this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('buildings', 3);
        $this->assertDatabaseCount('spaces', 6);
        $this->assertSame(3, Building::distinct()->count('uuid'));
    }

    public function test_draft_autosave_resume_and_two_tab_conflict_do_not_change_live_structure(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure('hotel', 2, 3);
        $path = $url.'/declaration-drafts/'.$d->id;
        $this->postJson($path, ['version' => 1, 'state' => $s])->assertOk()->assertJsonPath('version', 2);
        $this->assertDatabaseCount('buildings', 0);
        $this->get($path)->assertOk()->assertSee('Atlas House');
        $this->postJson($path, ['version' => 1, 'state' => $s])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->commit($url, $d, $s, 2)->assertOk();
    }

    public function test_changing_structure_archives_removed_data_and_restores_same_uuids_from_history(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $original = $this->structure('hotel', 2, 2);
        $this->commit($url, $d, $original)->assertOk();
        $b = Building::sole();
        $uuid = $b->uuid;
        $oldIds = Space::pluck('id', 'uuid')->all();
        $d = $this->draft($url, $b);
        $s = $d->state;
        $s['identity']['name'] = 'Changed hotel';
        $s['levels'] = [$s['levels'][1]];
        $s['levels'][0]['spaces'][0]['name'] = 'Renamed room';
        $s['levels'][0]['spaces'][] = $this->space('technical', 'Plant room');
        $this->commit($url, $d, $s)->assertOk();
        $this->assertSame($uuid, $b->fresh()->uuid);
        $this->assertSame(1, Floor::count());
        $this->assertSame(2, Floor::withoutGlobalScopes()->count());
        $this->assertSame(3, Space::count());
        $this->assertDatabaseCount('spaces', 5);
        $this->assertDatabaseCount('building_revisions', 2);
        $latest = $b->revisions()->latest('id')->first();
        $this->getJson($url.'/declarations/'.$uuid.'/revisions/'.$latest->id.'/export?side=before')->assertOk()->assertJsonPath('structure.identity.name', 'Atlas House');
        $first = $b->revisions()->oldest('id')->first();
        $export = $this->getJson($url.'/declarations/'.$uuid.'/revisions/'.$first->id.'/export')->assertOk()->json('structure');
        $d = $this->draft($url, $b->fresh());
        $this->commit($url, $d, $export)->assertOk();
        $this->assertSame(2, Floor::count());
        $this->assertSame(4, Space::count());
        foreach ($oldIds as $key => $id) {
            $this->assertSame($id, Space::where('uuid', $key)->value('id'));
        }
    }

    public function test_different_layouts_basements_mezzanines_attics_and_unplaced_spaces_roundtrip(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure('mixed', 4, 0);
        foreach (['basement', 'ground', 'mezzanine', 'attic'] as $i => $kind) {
            $s['levels'][$i]['kind'] = $kind;
            for ($j = 0; $j < $i + 1; $j++) {
                $s['levels'][$i]['spaces'][] = $this->space($i === 0 ? 'technical' : 'common', $kind.' '.$j);
            }
        }$s['unplaced_spaces'][] = $this->space('storage', 'External store');
        $this->commit($url, $d, $s)->assertOk();
        $this->assertSame(11, Space::count());
        $this->assertSame(1, Space::whereNull('floor_id')->count());
        $b = Building::sole();
        $export = $this->getJson($url.'/declarations/'.$b->uuid.'/export')->assertOk()->json('structure');
        $this->assertSame([1, 2, 3, 4], array_map(fn ($l) => count($l['spaces']), $export['levels']));
        $this->assertSame('basement', $export['levels'][0]['kind']);
    }

    public function test_invalid_coordinates_year_keys_and_relations_are_atomic(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure();
        $s['identity']['latitude'] = 42;
        $this->commit($url, $d, $s)->assertUnprocessable()->assertJsonValidationErrors('coordinates');
        $s['identity']['longitude'] = 20;
        $s['identity']['construction_year'] = date('Y') + 1;
        $this->commit($url, $d, $s)->assertUnprocessable();
        $s['identity']['construction_year'] = null;
        $s['levels'][] = $s['levels'][0];
        $this->commit($url, $d, $s)->assertUnprocessable()->assertJsonValidationErrors('structure');
        $this->assertDatabaseCount('buildings', 0);
        $this->assertDatabaseCount('building_revisions', 0);
    }

    public function test_roles_property_grants_and_capability_denials_apply_to_editor_draft_and_export(): void
    {
        [$owner,$o,$p,$url] = $this->fixture();
        [$member] = $this->fixture();
        $membership = $o->memberships()->create(['user_id' => $member->id, 'role' => 'member', 'access_scope' => 'properties']);
        $this->login($owner);
        $d = $this->draft($url);
        $this->commit($url, $d, $this->structure())->assertOk();
        $b = Building::sole();
        $this->login($member)->get($url.'/declarations/'.$b->uuid)->assertForbidden();
        $membership->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'viewer']);
        $this->get($url.'/declarations/'.$b->uuid)->assertOk();
        $this->get($url.'/declarations/'.$b->uuid.'/edit')->assertForbidden();
        $this->postJson($url.'/declaration-drafts/'.$d->id, ['version' => 2, 'state' => $this->structure()])->assertForbidden();
        $membership->propertyAccesses()->update(['permission' => 'editor']);
        $this->get($url.'/declarations/'.$b->uuid.'/edit')->assertRedirect();
        $o->capabilityOverrides()->create(['capability' => 'hospitality.rooms', 'enabled' => false]);
        $this->get($url.'/declarations/'.$b->uuid.'/export')->assertForbidden();
        $this->get($url.'/declarations/'.$b->uuid)->assertOk()->assertDontSee('Room 1');
        $o->capabilityOverrides()->create(['capability' => 'hospitality.buildings', 'enabled' => false]);
        $this->get($url.'/declarations/'.$b->uuid)->assertForbidden();
    }

    public function test_cross_tenant_resource_keys_drafts_and_revisions_cannot_be_stolen(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        [$foreign,$fo,$fp,$foreignUrl] = $this->fixture();
        $this->login($foreign);
        $fd = $this->draft($foreignUrl);
        $this->commit($foreignUrl, $fd, $this->structure())->assertOk();
        $fb = Building::sole();
        $foreignSpace = Space::sole();
        $this->login($u);
        $d = $this->draft($url);
        $this->get($url.'/declarations/'.$fb->uuid)->assertNotFound();
        $this->get($foreignUrl.'/declaration-drafts/'.$fd->id)->assertForbidden();
        $this->get($url.'/declaration-drafts/'.$fd->id)->assertNotFound();
        $s = $this->structure();
        $s['levels'][0]['spaces'][0]['key'] = $foreignSpace->uuid;
        $this->commit($url, $d, $s)->assertUnprocessable()->assertJsonValidationErrors('structure');
        $this->assertDatabaseCount('buildings', 1);
    }

    public function test_legacy_mutations_are_audited_and_make_stale_declaration_drafts_conflict(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $this->commit($url, $d, $this->structure())->assertOk();
        $b = Building::sole();
        $edit = $this->draft($url, $b);
        $this->post($url.'/buildings/'.$b->id.'/floors', ['name' => 'New level', 'position' => 8])->assertRedirect();
        $this->commit($url, $edit, $edit->state)->assertUnprocessable()->assertJsonValidationErrors('building');
        $this->assertDatabaseHas('building_revisions', ['entity_type' => 'floor', 'action' => 'created']);
        $this->get($url.'/declarations/'.$b->uuid.'/edit')->assertRedirect();
        $this->assertSame(3, BuildingDeclarationDraft::count());
    }

    public function test_archived_building_denies_legacy_mutations_and_can_be_reactivated_in_studio(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure();
        $s['identity']['state'] = 'archived';
        $this->commit($url, $d, $s)->assertOk();
        $b = Building::sole();
        $this->post($url.'/buildings/'.$b->id.'/floors', ['name' => 'No', 'position' => 0])->assertForbidden();
        $d = $this->draft($url, $b);
        $s = $d->state;
        $s['identity']['state'] = 'active';
        $this->commit($url, $d, $s)->assertOk();
        $this->assertSame('active', $b->fresh()->state);
        $this->assertSame(1, Space::count());
    }

    public function test_manual_assertions_are_unverified_and_existing_uuid_is_immutable(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure();
        $s['identity']['construction_year'] = 1990;
        $s['identity']['verification_state'] = 'verified';
        $s['identity']['uuid'] = (string) Str::uuid();
        $this->commit($url, $d, $s)->assertOk();
        $b = Building::sole();
        $this->assertSame('unverified', $b->verification_state);
        $this->assertDatabaseHas('building_assertions', ['building_id' => $b->id, 'field_key' => 'construction_year', 'source_kind' => 'manual', 'verification_state' => 'unverified']);
        $this->assertNotSame($s['identity']['uuid'], $b->uuid);
        $this->expectException(\LogicException::class);
        $b->uuid = (string) Str::uuid();
        $b->save();
    }

    public function test_large_json_import_is_bounded_batched_and_persistent(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure('hotel', 100, 50);
        $start = microtime(true);
        $this->postJson($url.'/declaration-drafts/'.$d->id, ['version' => 1, 'state' => $s, 'source_kind' => 'import'])->assertOk()->assertJsonPath('state.identity.name', 'Atlas House');
        $this->commit($url, $d, $s, 2)->assertOk();
        $this->assertDatabaseCount('floors', 100);
        $this->assertDatabaseCount('spaces', 5000);
        $this->assertDatabaseCount('building_revisions', 1);
        fwrite(STDOUT, sprintf("\nLarge declaration (100 levels / 5000 spaces): %.2fs\n", microtime(true) - $start));
    }

    public function test_declaration_endpoints_require_verification_customer_guard_and_csrf(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->get($url.'/building-studio')->assertRedirect(route('login'));
        $u->forceFill(['email_verified_at' => null])->save();
        $this->login($u)->get($url.'/building-studio')->assertRedirect(route('verification.notice'));
        $u->forceFill(['email_verified_at' => now()])->save();
        $this->login($u);
        $d = $this->draft($url);
        $this->app->detectEnvironment(fn () => 'local');
        $this->commit($url, $d, $this->structure())->assertStatus(419);
        $this->app->detectEnvironment(fn () => 'testing');
        $this->assertDatabaseCount('buildings', 0);
    }

    public function test_draft_accepts_an_incomplete_accommodation_unit_but_final_declaration_requires_its_links(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure();
        $s['units'][] = ['key' => (string) Str::uuid(), 'name' => 'Suite in progress', 'unit_type' => 'suite', 'space_keys' => [], 'bed_key' => null];
        $this->postJson($url.'/declaration-drafts/'.$d->id, ['version' => 1, 'state' => $s, 'stage' => 'structure'])->assertOk();
        $this->assertSame('structure', $d->fresh()->stage);
        $this->commit($url, $d, $s, 2)->assertUnprocessable()->assertJsonValidationErrors('units.0.space_keys');
        $this->assertDatabaseCount('buildings', 0);
    }

    public function test_migration_backfills_existing_records_without_inventing_building_facts(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $migration = require database_path('migrations/2026_11_16_160000_add_building_declarations.php');
        $migration->down();
        $id = DB::table('buildings')->insertGetId(['property_id' => $p->id, 'name' => 'Existing building']);
        $floorId = DB::table('floors')->insertGetId(['building_id' => $id, 'name' => 'Known basement', 'position' => -1]);
        $spaceId = DB::table('spaces')->insertGetId(['property_id' => $p->id, 'building_id' => $id, 'floor_id' => $floorId, 'name' => 'Existing store', 'space_type' => 'storage']);
        $legacy = [];
        for ($i = 0; $i < 500; $i++) {
            $legacy[] = ['property_id' => $p->id, 'building_id' => $id, 'floor_id' => $floorId, 'name' => 'Legacy '.$i, 'space_type' => 'room', 'area_m2' => 12.5];
        }
        foreach (array_chunk($legacy, 250) as $batch) {
            DB::table('spaces')->insert($batch);
        }
        $migration->up();
        $b = Building::findOrFail($id);
        $this->assertTrue(Str::isUuid($b->uuid));
        $this->assertNull($b->construction_year);
        $this->assertNull($b->classification);
        $this->assertNull($b->country);
        $this->assertSame(-1, Floor::find($floorId)->position);
        $this->assertTrue(Str::isUuid(Space::find($spaceId)->uuid));
        $this->assertSame(501, Space::count());
        $this->assertSame(501, Space::distinct()->count('uuid'));
        $this->assertSame(500, Space::where('area_m2', 12.5)->count());
        $this->assertDatabaseHas('building_revisions', ['building_id' => $id, 'source_kind' => 'legacy', 'action' => 'identity_assigned']);
    }

    public function test_unknown_classification_stays_unknown_and_identity_does_not_gain_false_facts(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $this->login($u);
        $d = $this->draft($url);
        $s = $this->structure();
        $s['identity']['classification'] = null;
        $this->commit($url, $d, $s)->assertOk();
        $b = Building::sole();
        $this->assertNull($b->classification);
        $this->assertNull($b->construction_year);
        $this->assertDatabaseMissing('building_assertions', ['field_key' => 'construction_year']);
    }

    public function test_building_history_cannot_be_overwritten(): void
    {
        [$u,$o,$p,$url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Existing']);
        $revision = $b->revisions()->first();
        $this->expectException(\LogicException::class);
        $revision->update(['source_kind' => 'document']);
    }
}
