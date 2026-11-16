<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\BuildingDeclarationDraft;
use App\Models\Organization;
use App\Models\Space;
use App\Models\User;
use App\Support\BuildingDeclarationSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
