<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\BuildingDeclarationDraft;
use App\Models\Organization;
use App\Models\Space;
use App\Models\User;
use App\Support\BuildingDeclarationSchema;
use App\Support\BuildingDeclarationStudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuildingLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'owner'): array
    {
        $u = User::factory()->create(['locale' => 'en', 'profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $o = Organization::create(['name' => 'Library organization']);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $m = $o->memberships()->create(['user_id' => $u->id, 'role' => $role]);
        $p = $o->properties()->create(['name' => 'Atlas property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_locale' => 'en']);

        return [$u, $o, $p, $m, '/app/organizations/'.$o->id];
    }

    public function test_library_links_buildings_directly_to_studio_and_filters_without_leaking_tenants(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Visible tower']);
        $p->buildings()->create(['name' => 'Old lodge', 'state' => 'archived']);
        $other = Organization::create(['name' => 'Other']);
        $foreign = $other->properties()->create(['name' => 'Foreign', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $foreign->buildings()->create(['name' => 'Hidden tower']);
        $this->get($url.'/spaces')->assertOk()->assertSee('Visible tower')->assertSee('Old lodge')->assertDontSee('Hidden tower')->assertSee(route('app.declarations.edit', [$o, $p, $b->uuid]), false)->assertDontSee('data-space-form', false);
        $this->get($url.'/spaces?q=Visible')->assertOk()->assertSee('Visible tower')->assertDontSee('Old lodge');
        $this->get($url.'/spaces?state=archived')->assertOk()->assertSee('Old lodge')->assertDontSee('Visible tower');
        $this->get($url.'/spaces?property='.$foreign->id)->assertOk()->assertDontSee('Hidden tower')->assertDontSee('Visible tower');
        $this->get($url.'/spaces?state=invalid')->assertSessionHasErrors('state');
    }

    public function test_viewer_library_is_read_only_and_property_grants_scope_every_building(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture('member');
        $m->update(['access_scope' => 'properties']);
        $m->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'viewer']);
        $b = $p->buildings()->create(['name' => 'Viewer building']);
        $hidden = $o->properties()->create(['name' => 'Restricted property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $hidden->buildings()->create(['name' => 'Restricted tower']);
        $this->get($url.'/spaces')->assertOk()->assertSee('Viewer building')->assertDontSee('Restricted tower')->assertDontSee('Restricted property')->assertSee(route('app.declarations.show', [$o, $p, $b->uuid]), false)->assertDontSee(route('app.declarations.edit', [$o, $p, $b->uuid]), false);
        $this->get(route('app.declarations.edit', [$o, $p, $b->uuid]))->assertForbidden();
    }

    public function test_old_space_link_opens_its_room_in_studio_and_resumes_existing_draft(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Existing hotel']);
        $s = $b->spaces()->create(['property_id' => $p->id, 'name' => 'Room 101', 'space_type' => 'room', 'area_m2' => 15, 'bed_count' => 2, 'capacity' => 2]);
        $edit = route('app.declarations.edit', [$o, $p, $b->uuid, 'space' => $s->uuid]);
        $this->get($url.'/properties/'.$p->id.'/spaces/'.$s->id.'/edit')->assertRedirect($edit);
        $response = $this->get($edit);
        $d = BuildingDeclarationDraft::sole();
        $this->assertSame('structure', $d->stage);
        $response->assertRedirect(route('app.declarations.editor', [$o, $p, $d->id, 'space' => $s->uuid]));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('data-focus-space="'.$s->uuid.'"', false)->assertSee('data-generate-default="area_m2"', false)->assertSee('data-generate-default="bed_count"', false)->assertSee('space='.$s->uuid.'&amp;lang=ro', false);
        $this->get($edit)->assertRedirect();
        $this->assertDatabaseCount('building_declaration_drafts', 1);
    }

    public function test_five_levels_fifty_rooms_persist_and_room_edit_preserves_identity_and_other_rooms(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $base = $url.'/properties/'.$p->id;
        $this->get($base.'/building-studio')->assertRedirect();
        $d = BuildingDeclarationDraft::sole();
        $state = app(BuildingDeclarationSchema::class)->empty();
        $state['identity'] = array_replace($state['identity'], ['name' => '50-room hotel', 'classification' => 'hotel', 'state' => 'active']);
        for ($i = 1; $i <= 5; $i++) {
            $level = ['key' => (string) Str::uuid(), 'name' => 'Level '.$i, 'kind' => 'upper', 'spaces' => []];
            for ($j = 1; $j <= 10; $j++) {
                $level['spaces'][] = ['key' => (string) Str::uuid(), 'name' => 'Room '.$i.str_pad($j, 2, '0', STR_PAD_LEFT), 'space_type' => 'room', 'number' => $i.str_pad($j, 2, '0', STR_PAD_LEFT), 'bed_count' => 2, 'capacity' => 2, 'area_m2' => 15, 'beds' => []];
            }
            $state['levels'][] = $level;
        }
        $this->postJson($base.'/declaration-drafts/'.$d->id.'/complete', ['version' => 1, 'state' => $state])->assertOk();
        $b = Building::sole();
        $uuid = $b->uuid;
        $this->assertSame(5, $b->floors()->count());
        $this->assertSame(50, $b->spaces()->count());
        $this->assertEquals(750, $b->spaces()->sum('area_m2'));
        $this->assertEquals(100, $b->spaces()->sum('bed_count'));
        $this->assertEquals(100, $b->spaces()->sum('capacity'));
        $this->get(route('app.declarations.edit', [$o, $p, $uuid]))->assertRedirect();
        $d = BuildingDeclarationDraft::whereNull('completed_at')->sole();
        $state = $d->state;
        $key = $state['levels'][0]['spaces'][0]['key'];
        $room = array_shift($state['levels'][0]['spaces']);
        $room['name'] = 'Junior suite';
        $room['area_m2'] = 27.5;
        $room['capacity'] = 3;
        $room['bed_count'] = 2;
        $state['levels'][1]['spaces'][] = $room;
        $state['identity']['display_name'] = 'Atlas Hotel';
        $this->postJson($base.'/declaration-drafts/'.$d->id.'/complete', ['version' => 1, 'state' => $state])->assertOk();
        $this->assertSame($uuid, $b->fresh()->uuid);
        $this->assertSame('Atlas Hotel', $b->fresh()->display_name);
        $this->assertDatabaseCount('spaces', 50);
        $this->assertDatabaseHas('spaces', ['uuid' => $key, 'name' => 'Junior suite', 'area_m2' => 27.5, 'capacity' => 3, 'floor_id' => $b->floors()->orderBy('position')->skip(1)->first()->id]);
        $this->assertSame(49, Space::where('area_m2', 15)->where('capacity', 2)->count());
        $this->assertSame(2, $b->revisions()->where('entity_type', 'declaration')->count());
    }

    public function test_property_space_assignment_preserves_uuid_and_rejects_other_properties_and_reassignment(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Target']);
        $s = $p->spaces()->create(['name' => 'Legacy common area', 'space_type' => 'common', 'area_m2' => 25]);
        $before = $s->uuid;
        $other = $o->properties()->create(['name' => 'Other property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $foreign = $other->buildings()->create(['name' => 'Wrong target']);
        $path = $url.'/properties/'.$p->id.'/spaces/'.$s->id.'/studio';
        $this->post($path, ['building_id' => $foreign->id])->assertSessionHasErrors('building_id');
        $this->assertNull($s->fresh()->building_id);
        $this->post($path, ['building_id' => $b->id])->assertRedirect(route('app.declarations.edit', [$o, $p, $b->uuid, 'space' => $before]));
        $this->assertSame($before, $s->fresh()->uuid);
        $this->assertSame($b->id, $s->fresh()->building_id);
        $this->assertEquals(25, $s->fresh()->area_m2);
        $this->assertSame(1, $b->revisions()->where('entity_type', 'space')->count());
        $this->post($path, ['building_id' => $b->id])->assertStatus(409);
        $this->get($url.'/properties/'.$other->id.'/spaces/'.$s->id.'/edit')->assertNotFound();
    }

    public function test_assignment_requires_editor_and_room_capability_and_rejects_archived_target(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Archived', 'state' => 'archived']);
        $s = $p->spaces()->create(['name' => 'Legacy', 'space_type' => 'common']);
        $path = $url.'/properties/'.$p->id.'/spaces/'.$s->id.'/studio';
        $this->post($path, ['building_id' => $b->id])->assertSessionHasErrors('building_id');
        $m->update(['role' => 'member']);
        $this->post($path, ['building_id' => $b->id])->assertForbidden();
        $m->update(['role' => 'owner']);
        $o->capabilityOverrides()->create(['capability' => 'hospitality.rooms', 'enabled' => false]);
        $this->post($path, ['building_id' => $b->id])->assertForbidden();
        $this->get($url.'/spaces')->assertForbidden();
    }

    public function test_confirmation_is_read_only_and_requires_positive_answer_and_current_version(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Confirm building']);
        $path = route('app.declarations.destroy', [$o, $p, $b->uuid]);
        $this->get($url.'/spaces')->assertOk()->assertSee('data-building-delete-dialog', false)->assertSee('Yes, delete building')->assertSee('Cancel');
        $this->get($path.'/delete')->assertNotFound();
        $this->assertNotNull(Building::find($b->id));
        $this->delete($path, ['confirmed' => 'no', 'version' => $b->fresh()->declaration_version])->assertSessionHasErrors('confirmed');
        $this->delete($path, ['confirmed' => '1'])->assertSessionHasErrors('version');
        $oldVersion = $b->fresh()->declaration_version;
        $b->update(['name' => 'Changed after confirmation']);
        $this->delete($path, ['confirmed' => '1', 'version' => $oldVersion])->assertSessionHasErrors('version');
        $this->assertNotNull(Building::find($b->id));
    }

    public function test_confirmed_deletion_removes_the_whole_building_from_workflows_but_preserves_identity_and_history(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Delete this hotel']);
        $other = $p->buildings()->create(['name' => 'Keep this hotel']);
        $floor = $b->floors()->create(['name' => 'Ground', 'position' => 0]);
        $space = $b->spaces()->create(['property_id' => $p->id, 'floor_id' => $floor->id, 'name' => 'Room 101', 'space_type' => 'room', 'bed_count' => 1, 'area_m2' => 15]);
        $bed = $space->beds()->create(['uuid' => (string) Str::uuid(), 'label' => 'Bed A']);
        $unit = $b->accommodationUnits()->create(['uuid' => (string) Str::uuid(), 'name' => 'Unit A', 'unit_type' => 'room']);
        $unit->spaces()->attach($space);
        $this->get(route('app.declarations.edit', [$o, $p, $b->uuid]))->assertRedirect();
        $draft = BuildingDeclarationDraft::sole();
        $historyCount = $b->revisions()->count();
        $version = $b->fresh()->declaration_version;
        $this->delete(route('app.declarations.destroy', [$o, $p, $b->uuid]), ['confirmed' => 1, 'version' => $version])->assertRedirect(route('app.spaces.index', $o))->assertSessionHas('status');
        $this->assertSoftDeleted('buildings', ['id' => $b->id, 'uuid' => $b->uuid]);
        $this->assertNull(Building::find($b->id));
        $this->assertSame($version + 1, Building::withTrashed()->find($b->id)->declaration_version);
        $this->assertDatabaseHas('spaces', ['id' => $space->id, 'uuid' => $space->uuid, 'area_m2' => 15]);
        $this->assertSame(0, $p->spaces()->count());
        $this->assertSame(0, $b->floors()->count());
        $this->assertSame(0, $space->beds()->count());
        $this->assertSame(0, $b->accommodationUnits()->count());
        $this->assertDatabaseHas('accommodation_unit_space', ['space_id' => $space->id, 'accommodation_unit_id' => $unit->id]);
        $this->assertSame($historyCount + 1, $b->revisions()->count());
        $deletion = $b->revisions()->where('action', 'deleted')->sole();
        $this->assertSame($u->id, $deletion->actor_id);
        $this->assertSame($space->uuid, $deletion->before_data['levels'][0]['spaces'][0]['key']);
        $this->get($url.'/spaces')->assertOk()->assertDontSee('<h3>Delete this hotel</h3>', false)->assertSee('Keep this hotel');
        $this->get(route('app.declarations.show', [$o, $p, $b->uuid]))->assertNotFound();
        $this->get(route('app.declarations.editor', [$o, $p, $draft->id]))->assertNotFound();
        $this->postJson(route('app.declarations.commit', [$o, $p, $draft->id]), ['version' => 1, 'state' => $draft->state])->assertNotFound();
        $this->assertNotNull(Building::find($other->id));
    }

    public function test_delete_is_denied_for_viewer_other_tenant_and_disabled_rooms(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Protected hotel']);
        $b->spaces()->create(['property_id' => $p->id, 'name' => 'Protected room', 'space_type' => 'room']);
        $path = route('app.declarations.destroy', [$o, $p, $b->uuid]);
        $data = ['confirmed' => 1, 'version' => $b->fresh()->declaration_version];
        $m->update(['role' => 'member']);
        $this->delete($path, $data)->assertForbidden();
        $this->get($url.'/spaces')->assertOk()->assertDontSee('data-building-delete data-building-name=', false);
        $m->update(['role' => 'owner']);
        $other = Organization::create(['name' => 'Foreign organization']);
        $foreign = $other->properties()->create(['name' => 'Foreign', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $this->delete(route('app.declarations.destroy', [$o, $foreign, $b->uuid]), $data)->assertNotFound();
        $this->delete(route('app.declarations.destroy', [$other, $foreign, $b->uuid]), $data)->assertNotFound();
        $o->capabilityOverrides()->create(['capability' => 'hospitality.rooms', 'enabled' => false]);
        $this->delete($path, $data)->assertForbidden();
        $this->assertNotNull(Building::find($b->id));
    }

    public function test_force_deletion_is_still_blocked_to_preserve_identity_history(): void
    {
        [$u, $o, $p] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Keep history']);
        $this->expectException(\LogicException::class);
        $b->forceDelete();
    }

    public function test_deleted_building_reference_remains_reserved_with_validation_instead_of_database_error(): void
    {
        [$u, $o, $p] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Reserved identity', 'reference' => 'HOTEL-ORIGINAL']);
        $this->delete(route('app.declarations.destroy', [$o, $p, $b->uuid]), ['confirmed' => 1, 'version' => $b->fresh()->declaration_version])->assertRedirect();
        $this->get(route('app.declarations.new', [$o, $p]))->assertRedirect();
        $draft = BuildingDeclarationDraft::sole();
        $state = $draft->state;
        $state['identity']['name'] = 'New building';
        $state['identity']['reference'] = 'HOTEL-ORIGINAL';
        $this->postJson(route('app.declarations.commit', [$o, $p, $draft->id]), ['version' => 1, 'state' => $state])->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertSame(0, Building::count());
        $this->assertSame(1, Building::withTrashed()->count());
    }

    public function test_trash_restores_existing_deletion_with_all_structure_data_and_prior_archives_preserved(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Restore hotel', 'classification' => 'hotel', 'state' => 'active', 'address' => 'Known address', 'construction_year' => null]);
        $floor = $b->floors()->create(['name' => 'Mezzanine', 'kind' => 'mezzanine', 'position' => 7]);
        $room = $b->spaces()->create(['property_id' => $p->id, 'floor_id' => $floor->id, 'name' => 'Suite 101', 'space_type' => 'room', 'number' => '101', 'bed_count' => 1, 'capacity' => 2, 'area_m2' => 27.5]);
        $unplaced = $b->spaces()->create(['property_id' => $p->id, 'name' => 'Technical', 'space_type' => 'technical', 'capacity' => 0]);
        $bed = $room->beds()->create(['uuid' => (string) Str::uuid(), 'label' => 'Bed A']);
        $unit = $b->accommodationUnits()->create(['uuid' => (string) Str::uuid(), 'name' => 'Suite A', 'unit_type' => 'suite']);
        $unit->spaces()->attach([$room->id, $unplaced->id]);
        $oldBed = $room->beds()->create(['uuid' => (string) Str::uuid(), 'label' => 'Old bed', 'archived_at' => now()->subDays(5)]);
        $oldUnit = $b->accommodationUnits()->create(['uuid' => (string) Str::uuid(), 'name' => 'Old unit', 'unit_type' => 'suite', 'archived_at' => now()->subDays(5)]);
        $oldFloor = $b->floors()->create(['name' => 'Previously removed floor', 'position' => 9, 'archived_at' => now()->subDays(5)]);
        $oldRoom = $b->spaces()->create(['property_id' => $p->id, 'floor_id' => $oldFloor->id, 'name' => 'Previously removed room', 'space_type' => 'room', 'archived_at' => now()->subDays(5)]);
        $before = app(BuildingDeclarationStudio::class)->snapshot($b->fresh());
        $this->delete(route('app.declarations.destroy', [$o, $p, $b->uuid]), ['confirmed' => 1, 'version' => $b->fresh()->declaration_version])->assertRedirect();
        $this->get($url.'/spaces/trash')->assertOk()->assertSee('Restore hotel')->assertSee('Restore building');
        $restore = route('app.declarations.restore', [$o, $p, $b->uuid]);
        $this->post($restore, ['version' => $b->fresh()->declaration_version])->assertRedirect(route('app.spaces.index', $o));
        $restored = Building::findOrFail($b->id);
        $this->assertSame($b->uuid, $restored->uuid);
        $this->assertSame($b->reference, $restored->reference);
        $this->assertSame($before, app(BuildingDeclarationStudio::class)->snapshot($restored));
        $this->assertDatabaseHas('floors', ['id' => $floor->id, 'uuid' => $floor->uuid, 'position' => 7, 'archived_at' => null]);
        $this->assertDatabaseHas('space_beds', ['id' => $bed->id, 'uuid' => $bed->uuid, 'label' => 'Bed A', 'archived_at' => null]);
        $this->assertDatabaseHas('accommodation_unit_space', ['accommodation_unit_id' => $unit->id, 'space_id' => $unplaced->id]);
        $this->assertNotNull(DB::table('spaces')->find($oldRoom->id)->archived_at);
        $this->assertNotNull(DB::table('floors')->find($oldFloor->id)->archived_at);
        $this->assertNotNull(DB::table('space_beds')->find($oldBed->id)->archived_at);
        $this->assertNotNull(DB::table('accommodation_units')->find($oldUnit->id)->archived_at);
        $this->assertSame($u->id, $restored->revisions()->where('action', 'restored')->sole()->actor_id);
        $this->get($url.'/spaces/trash')->assertOk()->assertDontSee('<h2>Restore hotel</h2>', false);
        $this->get(route('app.declarations.edit', [$o, $p, $b->uuid]))->assertRedirect();
        $deletion = $restored->revisions()->where('action', 'deleted')->sole();
        $restoration = $restored->revisions()->where('action', 'restored')->sole();
        $this->get(route('app.declarations.show', [$o, $p, $b->uuid]))->assertOk()
            ->assertDontSee(route('app.declarations.revision', [$o, $p, $b->uuid, $deletion->id]).'"', false)
            ->assertSee(route('app.declarations.revision', [$o, $p, $b->uuid, $restoration->id]), false);
        $this->get(route('app.declarations.revision', [$o, $p, $b->uuid, $deletion->id, 'side' => 'before']))->assertOk();
        $this->post($restore, ['version' => $restored->declaration_version])->assertNotFound();
    }

    public function test_repeated_delete_restore_cycles_preserve_state_and_do_not_duplicate_structure(): void
    {
        [$u, $o, $p] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Repeat', 'state' => 'draft']);
        $room = $b->spaces()->create(['property_id' => $p->id, 'name' => 'Room', 'space_type' => 'room', 'area_m2' => 15]);
        for ($i = 0; $i < 2; $i++) {
            $this->delete(route('app.declarations.destroy', [$o, $p, $b->uuid]), ['confirmed' => 1, 'version' => $b->fresh()->declaration_version])->assertRedirect();
            $this->post(route('app.declarations.restore', [$o, $p, $b->uuid]), ['version' => $b->fresh()->declaration_version])->assertRedirect();
        }
        $this->assertSame('draft', $b->fresh()->state);
        $this->assertSame($room->uuid, $b->spaces()->sole()->uuid);
        $this->assertSame(2, $b->revisions()->where('action', 'restored')->count());
        $this->assertDatabaseCount('spaces', 1);
    }

    public function test_trash_and_restore_enforce_tenant_membership_property_grants_and_capabilities(): void
    {
        [$u, $o, $p, $m, $url] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Private trash']);
        $b->spaces()->create(['property_id' => $p->id, 'name' => 'Room', 'space_type' => 'room']);
        $this->delete(route('app.declarations.destroy', [$o, $p, $b->uuid]), ['confirmed' => 1, 'version' => $b->fresh()->declaration_version])->assertRedirect();
        $path = route('app.declarations.restore', [$o, $p, $b->uuid]);
        $data = ['version' => $b->fresh()->declaration_version];
        $m->update(['role' => 'member', 'access_scope' => 'properties']);
        $m->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'viewer']);
        $this->get($url.'/spaces/trash')->assertOk()->assertSee('Private trash')->assertDontSee($path, false);
        $this->post($path, $data)->assertForbidden();
        $m->propertyAccesses()->delete();
        $this->get($url.'/spaces/trash')->assertOk()->assertDontSee('Private trash');
        $this->post($path, $data)->assertForbidden();
        $m->update(['role' => 'owner']);
        $o->capabilityOverrides()->create(['capability' => 'hospitality.rooms', 'enabled' => false]);
        $this->get($url.'/spaces/trash')->assertForbidden();
        $this->post($path, $data)->assertForbidden();
        $this->assertSoftDeleted($b);
    }

    public function test_restore_failure_is_atomic_for_stale_version_missing_history_or_missing_structure(): void
    {
        [$u, $o, $p] = $this->fixture();
        $b = $p->buildings()->create(['name' => 'Atomic restore']);
        $floor = $b->floors()->create(['name' => 'Ground', 'position' => 0]);
        $this->delete(route('app.declarations.destroy', [$o, $p, $b->uuid]), ['confirmed' => 1, 'version' => $b->fresh()->declaration_version])->assertRedirect();
        $version = $b->fresh()->declaration_version;
        $path = route('app.declarations.restore', [$o, $p, $b->uuid]);
        $this->post($path, ['version' => $version - 1])->assertSessionHasErrors('version');
        $revision = $b->revisions()->where('action', 'deleted')->sole();
        $state = $revision->before_data;
        $state['unplaced_spaces'][] = ['key' => (string) Str::uuid(), 'name' => 'Missing room', 'space_type' => 'room', 'beds' => []];
        DB::table('building_revisions')->where('id', $revision->id)->update(['before_data' => json_encode($state)]);
        $this->post($path, ['version' => $version])->assertSessionHasErrors('building');
        $this->assertNotNull(DB::table('floors')->find($floor->id)->archived_at);
        $this->assertSoftDeleted($b);
        DB::table('building_revisions')->where('id', $revision->id)->update(['before_data' => null]);
        $this->post($path, ['version' => $version])->assertSessionHasErrors('building');
        $this->assertSame(0, $b->revisions()->where('action', 'restored')->count());
    }
}
