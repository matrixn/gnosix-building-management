<?php

namespace Tests\Feature;

use App\Assets\EvidenceBridge;
use App\Assets\Registry;
use App\Evidence\Engine;
use App\Evidence\Facts;
use App\Models\Asset;
use App\Models\AssetFactApplication;
use App\Models\AssetPhoto;
use App\Models\AssetType;
use App\Models\Organization;
use App\Models\User;
use App\Support\BuildingDeclarationStudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssetRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'owner', string $propertyType = 'hotel'): array
    {
        Storage::fake('evidence');
        Http::preventStrayRequests();
        $u = User::factory()->create(['locale' => 'en', 'profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $o = Organization::create(['name' => 'Asset operator']);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $m = $o->memberships()->create(['user_id' => $u->id, 'role' => $role]);
        $p = $o->properties()->create(['name' => 'Atlas property', 'property_type' => $propertyType, 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b = $p->buildings()->create(['name' => 'Building One', 'state' => 'active']);
        $f = $b->floors()->create(['name' => 'Ground', 'position' => 0]);
        $s = $p->spaces()->create(['building_id' => $b->id, 'floor_id' => $f->id, 'name' => 'Room 101', 'space_type' => 'room']);
        $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_locale' => 'en']);

        return [$u, $o, $p, $b, $f, $s, $m];
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'Air conditioner', 'asset_type_id' => AssetType::where('key', 'hvac')->value('id'), 'status' => 'unknown'], $overrides);
    }

    private function create($u, $p, array $data = []): Asset
    {
        return app(Registry::class)->save($u, $p, $this->data($data));
    }

    private function source($u, $b, string $value = 'ABC123', string $identifier = 'CT-01')
    {
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('technical.png', 30, 30)], 'invoice')[0]['version'];
        $a = app(Engine::class)->propose($u, $b, ['semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => $identifier, 'value' => $value, 'quote' => 'Serial '.$value, 'page' => 1], $v);

        return [$a, $v];
    }

    public function test_one_asset_in_private_house_preserves_unknowns_and_uuid_across_edit_archive_restore(): void
    {
        [$u,$o,$p] = $this->fixture(propertyType: 'house');
        $this->post(route('app.assets.store', $o), $this->data(['property_id' => $p->id]))->assertRedirect();
        $a = Asset::sole();
        $uuid = $a->uuid;
        $ref = $a->reference;
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertNull($a->manual_data['serial_number']);
        $this->assertNull($a->manual_data['installation_date']);
        $this->assertNull($a->building_id);
        $url = route('app.assets.show', [$o, $uuid]);
        $this->get($url)->assertOk()->assertSee('Asset passport')->assertSee($ref);
        $this->get(route('app.assets.edit', [$o, $uuid]))->assertOk()->assertSee('data-asset-form', false);
        $this->patch(route('app.assets.update', [$o, $uuid]), $this->data(['revision' => 1, 'name' => 'Living room cooling', 'attributes' => ['power_kw' => '2.5']]))->assertRedirect();
        $this->assertSame(2.5, $a->fresh()->manual_data['attributes']['power_kw']);
        $this->post(route('app.assets.archive', [$o, $uuid]), ['revision' => 2, 'archived' => 1])->assertRedirect();
        $this->assertNotNull($a->fresh()->archived_at);
        $this->get(route('app.assets.index', $o))->assertDontSee('Living room cooling');
        $this->get(route('app.assets.index', [$o, 'state' => 'archived']))->assertSee('Living room cooling');
        $this->post(route('app.assets.archive', [$o, $uuid]), ['revision' => 3, 'archived' => 0])->assertRedirect();
        $this->assertNull($a->fresh()->archived_at);
        $this->assertSame($uuid, $a->fresh()->uuid);
        $this->assertSame($ref, $a->fresh()->reference);
        $this->assertDatabaseCount('asset_audits', 4);
    }

    public function test_multiple_room_assets_and_shared_system_span_buildings_without_fixed_hierarchy(): void
    {
        [$u,$o,$p,$b,$f,$s] = $this->fixture();
        $b2 = $p->buildings()->create(['name' => 'Building Two', 'state' => 'active']);
        $s2 = $p->spaces()->create(['building_id' => $b2->id, 'name' => 'Room 201', 'space_type' => 'room']);
        $system = $this->create($u, $p, ['name' => 'Shared heating', 'is_system' => true, 'served_spaces' => [$s->id, $s2->id]]);
        $a = $this->create($u, $p, ['name' => 'Room AC', 'space_id' => $s->id, 'system_id' => $system->id]);
        $this->create($u, $p, ['name' => 'Room TV', 'asset_type_id' => AssetType::where('key', 'appliance')->value('id'), 'space_id' => $s->id]);
        $this->assertSame($b->id, $a->building_id);
        $this->assertSame($f->id, $a->floor_id);
        $this->assertSame(2, $system->servedSpaces()->count());
        $this->assertNull($system->building_id);
        $this->get(route('app.assets.room', [$o, $p, $s->uuid]))->assertOk()->assertJsonCount(3, 'items');
        $this->get(route('app.assets.room', [$o, $p, $s2->uuid]))->assertOk()->assertJsonCount(1, 'items');
        $this->get(route('app.assets.index', [$o, 'building' => $b2->id]))->assertSee('Shared heating')->assertDontSee('Room TV');
        $this->get(route('app.assets.show', [$o, $system->uuid]))->assertOk()->assertSee('Room 201')->assertSee('Room AC');
    }

    public function test_duplicate_serials_require_explicit_acknowledgment_and_missing_serials_never_merge(): void
    {
        [$u,$o,$p] = $this->fixture();
        $a = $this->create($u, $p, ['serial_number' => '  abc123 ']);
        $this->assertSame('ABC123', $a->serial_key);
        try {
            $this->create($u, $p, ['serial_number' => 'ABC123']);
            $this->fail('Duplicate not acknowledged');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('serial_number', $e->errors());
        }
        $other = $this->create($u, $p, ['serial_number' => 'ABC123', 'duplicates_ack' => 1]);
        $this->assertNotSame($a->uuid, $other->uuid);
        $this->get(route('app.assets.show', [$o, $other->uuid]))->assertSee('Possible duplicates');
        $this->create($u, $p);
        $this->create($u, $p);
        $this->assertDatabaseCount('assets', 4);
        $this->assertSame(2, Asset::whereNull('serial_key')->count());
    }

    public function test_cross_tenant_grants_and_capability_boundaries_cover_routes_lookups_and_sources(): void
    {
        [$u,$o,$p,$b,$f,$s,$m] = $this->fixture('member');
        $m->update(['access_scope' => 'properties']);
        $m->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'editor']);
        $a = $this->create($u, $p);
        $hidden = $o->properties()->create(['name' => 'Hidden property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $other = User::factory()->create();
        $o->memberships()->create(['user_id' => $other->id, 'role' => 'owner']);
        $foreign = $this->create($other, $hidden, ['name' => 'Secret equipment']);
        $this->get(route('app.assets.index', $o))->assertOk()->assertSee('Air conditioner')->assertDontSee('Secret equipment');
        $this->get(route('app.assets.show', [$o, $foreign->uuid]))->assertForbidden();
        $this->get(route('app.assets.lookup', [$o, 'kind' => 'space', 'property' => $hidden->id]))->assertForbidden();
        $this->get(route('app.assets.room', [$o, $hidden, $s->uuid]))->assertNotFound();
        $m->propertyAccesses()->update(['permission' => 'viewer']);
        $this->get(route('app.assets.show', [$o, $a->uuid]))->assertOk()->assertDontSee('data-asset-form', false);
        $this->patch(route('app.assets.update', [$o, $a->uuid]), $this->data(['revision' => 1]))->assertForbidden();
        $this->post(route('app.assets.store', $o), $this->data(['property_id' => $p->id]))->assertForbidden();
        $o->capabilityOverrides()->create(['capability' => 'assets.view', 'enabled' => false]);
        $this->get(route('app.assets.show', [$o, $a->uuid]))->assertForbidden();
        $this->get(route('app.assets.lookup', [$o, 'kind' => 'property']))->assertForbidden();
    }

    public function test_invalid_location_and_system_cycles_are_rejected_transactionally(): void
    {
        [$u,$o,$p,$b,$f,$s] = $this->fixture();
        $otherB = $p->buildings()->create(['name' => 'Other']);
        try {
            $this->create($u, $p, ['space_id' => $s->id, 'building_id' => $otherB->id]);
            $this->fail('Mismatched location');
        } catch (ValidationException) {
            $this->assertDatabaseCount('assets', 0);
        }
        $a = $this->create($u, $p, ['is_system' => true]);
        $bAsset = $this->create($u, $p, ['is_system' => true, 'system_id' => $a->id]);
        try {
            app(Registry::class)->save($u, $p, $this->data(['revision' => 1, 'is_system' => true, 'system_id' => $bAsset->id]), $a);
            $this->fail('System cycle');
        } catch (ValidationException) {
            $this->assertNull($a->fresh()->system_id);
        }
        $this->patch(route('app.assets.update', [$o, $a->uuid]), $this->data(['revision' => 99]))->assertSessionHasErrors('revision');
        $this->assertSame(1, $a->fresh()->revision);
    }

    public function test_approved_evidence_is_separate_and_contradictions_never_silently_replace_values(): void
    {
        [$u,$o,$p,$b] = $this->fixture();
        $a = $this->create($u, $p, ['serial_number' => 'MANUAL']);
        [$fact,$v] = $this->source($u, $b);
        $bridge = app(EvidenceBridge::class);
        $link = $bridge->link($u, $a, $fact, 1);
        $this->assertNull($a->fresh()->manual_data['manufacturer']);
        try {
            $bridge->apply($u, $a, $link, 2, 1, 'Checked invoice');
            $this->fail('Unconfirmed source');
        } catch (ValidationException) {
            $this->assertDatabaseCount('asset_fact_applications', 0);
        }
        app(Engine::class)->review($u, $b, $fact->id, 1, 'confirm');
        try {
            $bridge->apply($u, $a, $link, 2, 2, 'Checked invoice');
            $this->fail('Overwrite without authorization');
        } catch (ValidationException) {
            $this->assertDatabaseCount('asset_fact_applications', 0);
        }
        $bridge->apply($u, $a, $link, 2, 2, 'Invoice checked, replacing approved layer', true);
        $app = AssetFactApplication::sole();
        $this->assertSame('ABC123', $app->value);
        $this->assertSame('MANUAL', $a->fresh()->manual_data['serial_number']);
        $this->assertTrue($bridge->valid($app));
        $this->get(route('app.assets.show', [$o, $a->uuid]))->assertOk()->assertSee('The manual value differs')->assertSee('technical.png');
        [$conflicting] = $this->source($u, $b, 'XYZ789');
        $this->assertFalse($bridge->valid($app->fresh()));
        $this->get(route('app.assets.show', [$o, $a->uuid]))->assertSee('Documentary contradictions exist')->assertSee('Source requires review again');
        app(Engine::class)->review($u, $b, $conflicting->id, 1, 'reject');
        $this->assertTrue($bridge->valid($app->fresh()));
        $bridge->withdraw($u, $a, $link, $a->fresh()->revision, 'Retracted after inspection');
        $this->assertSame('withdrawn', $app->fresh()->status);
        $this->assertSame('confirmed', $fact->fresh()->status);
    }

    public function test_evidence_assisted_import_needs_confirmation_and_never_applies_or_calls_ai(): void
    {
        [$u,$o,$p,$b] = $this->fixture();
        [$fact] = $this->source($u, $b);
        $url = route('app.assets.import', $o);
        $this->get($url)->assertOk()->assertSee('CT-01');
        $this->get($url.'?candidate='.$fact->id)->assertOk()->assertSee('ABC123');
        $data = ['candidate' => $fact->id, 'ids' => [$fact->id], 'name' => 'Boiler CT-01', 'asset_type_id' => AssetType::where('key', 'heating')->value('id')];
        $this->post($url, $data)->assertSessionHasErrors('confirmed');
        $this->assertDatabaseCount('assets', 0);
        $this->post($url, $data + ['confirmed' => 1])->assertRedirect();
        $a = Asset::sole();
        $this->assertSame('CT-01', $a->external_reference);
        $this->assertNull($a->manual_data['serial_number']);
        $this->assertDatabaseCount('asset_evidence_links', 1);
        $this->assertDatabaseCount('asset_fact_applications', 0);
        $this->post($url, $data + ['confirmed' => 1])->assertSessionHasErrors('duplicates_ack');
        $this->assertDatabaseCount('assets', 1);
        $this->post($url, $data + ['confirmed' => 1, 'asset_id' => $a->id])->assertRedirect();
        $this->assertDatabaseCount('asset_evidence_links', 1);
        Http::assertNothingSent();
    }

    public function test_custom_types_attributes_and_schema_evolution_preserve_declared_values(): void
    {
        [$u,$o,$p] = $this->fixture();
        $schema = [['key' => 'litres', 'label' => 'Tank capacity', 'type' => 'number', 'unit' => 'L']];
        $url = route('app.assets.types.store', $o);
        $this->post($url, ['name' => 'Water tank', 'attribute_schema' => json_encode($schema)])->assertRedirect();
        $type = AssetType::where('organization_id', $o->id)->sole();
        $this->get(route('app.assets.types', $o))->assertOk()->assertSee('Water tank');
        $a = $this->create($u, $p, ['asset_type_id' => $type->id, 'attributes' => ['litres' => '120']]);
        $this->assertSame(120, $a->manual_data['attributes']['litres']);
        $this->patch(route('app.assets.types.update', [$o, $type]), ['name' => 'Water tank', 'attribute_schema' => '[]', 'revision' => '1'])->assertRedirect();
        app(Registry::class)->save($u, $p, $this->data(['asset_type_id' => $type->id, 'revision' => 1]), $a);
        $this->assertSame(120, $a->fresh()->manual_data['attributes']['litres']);
        $this->post($url, ['name' => 'Unsafe', 'attribute_schema' => '[{"key":"bad key","label":"Bad","type":"script"}]'])->assertSessionHasErrors();
        $this->patch(route('app.assets.types.update', [$o, AssetType::whereNull('organization_id')->first()]), ['name' => 'Override', 'attribute_schema' => '[]', 'revision' => 1])->assertForbidden();
    }

    public function test_private_photos_validate_content_signed_expiry_and_scoped_viewer(): void
    {
        [$u,$o,$p] = $this->fixture();
        $a = $this->create($u, $p);
        $url = route('app.assets.photos', [$o, $a->uuid]);
        $this->post($url, ['revision' => 1, 'photos' => [UploadedFile::fake()->createWithContent('evil.png', '<svg onload=alert(1)>')]])->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('asset_photos', 0);
        $this->post($url, ['revision' => 1, 'photos' => [UploadedFile::fake()->image('equipment.png', 40, 40)], 'caption' => 'Nameplate'])->assertRedirect();
        $photo = AssetPhoto::sole();
        Storage::disk('evidence')->assertExists($photo->path);
        $link = URL::temporarySignedRoute('app.assets.photo', now()->addMinutes(10), [$o, $a->uuid, $photo->uuid, 'viewer' => $u->id], false);
        $this->get($link)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('app.assets.photo', [$o, $a->uuid, $photo->uuid]))->assertForbidden();
        $this->get(route('app.assets.show', [$o, $a->uuid]))->assertOk()->assertSee('Nameplate');
        $this->post(route('app.assets.photos.archive', [$o, $a->uuid, $photo->uuid]), ['revision' => 2, 'archived' => 1])->assertRedirect();
        $this->get($link)->assertForbidden();
        $this->post(route('app.assets.photos.archive', [$o, $a->uuid, $photo->uuid]), ['revision' => 3, 'archived' => 0])->assertRedirect();
        $this->assertNull($photo->fresh()->archived_at);
        $this->get($link)->assertOk();
        $this->travel(11)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_building_soft_delete_restores_assets_and_permanent_purge_archives_identity_without_erasing_passport(): void
    {
        [$u,$o,$p,$b,$f,$s] = $this->fixture();
        $a = $this->create($u, $p, ['space_id' => $s->id]);
        [$fact,$v] = $this->source($u, $b);
        $bridge = app(EvidenceBridge::class);
        $link = $bridge->link($u, $a, $fact, 1);
        app(Engine::class)->review($u, $b, $fact->id, 1, 'confirm');
        $bridge->apply($u, $a, $link, 2, 2, 'Verified original');
        $studio = app(BuildingDeclarationStudio::class);
        $uuid = $a->uuid;
        $studio->delete($u, $p, $b, $b->fresh()->declaration_version);
        $this->get(route('app.assets.show', [$o, $uuid]))->assertForbidden();
        $studio->restore($u, $p, $b, $b->fresh()->declaration_version);
        $this->get(route('app.assets.show', [$o, $uuid]))->assertOk();
        $studio->delete($u, $p, $b, $b->fresh()->declaration_version);
        $studio->purge($u, $p, $b, $b->fresh()->declaration_version);
        $this->assertSame($uuid, $a->fresh()->uuid);
        $this->assertNull($a->fresh()->building_id);
        $this->assertNull($a->fresh()->space_id);
        $this->assertNotNull($a->fresh()->archived_at);
        $this->assertNull($link->fresh()->assertion_id);
        $this->assertSame('withdrawn', AssetFactApplication::sole()->status);
        $this->get(route('app.assets.show', [$o, $uuid]))->assertOk()->assertSee('Source removed by building retention');
    }

    public function test_ten_thousand_assets_are_paginated_searched_and_never_serialized_as_one_collection(): void
    {
        [$u,$o,$p] = $this->fixture();
        $type = AssetType::first()->id;
        $start = microtime(true);
        $rows = [];
        for ($i = 1; $i <= 10000; $i++) {
            $uuid = (string) Str::uuid();
            $rows[] = ['uuid' => $uuid, 'organization_id' => $o->id, 'property_id' => $p->id, 'asset_type_id' => $type, 'reference' => 'TEST-'.str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'name' => 'Asset '.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'serial_key' => 'SERIAL-'.$i, 'manual_data' => '{}', 'created_at' => now(), 'updated_at' => now()];
            if (count($rows) === 200) {
                DB::table('assets')->insert($rows);
                $rows = [];
            }
        }
        $response = $this->get(route('app.assets.index', $o))->assertOk();
        $assets = $response->viewData('assets');
        $this->assertSame(10000, $assets->total());
        $this->assertCount(24, $assets->items());
        $this->assertLessThan(180000, strlen($response->getContent()));
        $this->get(route('app.assets.index', [$o, 'q' => 'SERIAL-9999']))->assertOk()->assertSee('Asset 09999')->assertDontSee('Asset 00001');
        $lookup = $this->getJson(route('app.assets.lookup', [$o, 'kind' => 'asset', 'property' => $p->id]))->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('more', true);
        $this->assertLessThan(10000, strlen($lookup->getContent()));
        fwrite(STDERR, "\nAsset dataset 10,000: ".round(microtime(true) - $start, 2)."s including inserts and HTTP checks\n");
    }

    public function test_all_hospitality_scales_use_the_same_model_and_nullable_structure(): void
    {
        foreach (['cabin', 'guesthouse', 'hostel', 'hotel', 'resort', 'mixed'] as $kind) {
            [$u,$o,$p] = $this->fixture(propertyType: $kind);
            $a = $this->create($u, $p, ['name' => 'Asset for '.$kind]);
            $this->assertNull($a->building_id);
            $this->assertNull($a->space_id);
            $this->get(route('app.assets.show', [$o, $a->uuid]))->assertOk()->assertSee('Asset for '.$kind);
        }
    }

    public function test_asset_review_has_independent_capability_and_source_scope_protection(): void
    {
        [$u,$o,$p,$b] = $this->fixture();
        $a = $this->create($u, $p);
        [$fact,$v] = $this->source($u, $b);
        app(Engine::class)->review($u, $b, $fact->id, 1, 'confirm');
        $link = app(EvidenceBridge::class)->link($u, $a, $fact, 1);
        $o->capabilityOverrides()->create(['capability' => 'assets.review', 'enabled' => false]);
        $url = route('app.assets.review', [$o, $a->uuid, $link]);
        $data = ['revision' => 2, 'source_revision' => 2, 'action' => 'apply', 'justification' => 'Verified'];
        $this->post($url, $data)->assertForbidden();
        $this->assertDatabaseCount('asset_fact_applications', 0);
        $o->capabilityOverrides()->where('capability', 'assets.review')->update(['enabled' => true]);
        $o->capabilityOverrides()->create(['capability' => 'assets.manage', 'enabled' => false]);
        $this->post($url, $data)->assertRedirect();
        $this->assertDatabaseCount('asset_fact_applications', 1);
        $o->capabilityOverrides()->where('capability', 'assets.manage')->update(['enabled' => true]);
        $p2 = $o->properties()->create(['name' => 'Other property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b2 = $p2->buildings()->create(['name' => 'Other building']);
        [$foreign,$foreignV] = $this->source($u, $b2, 'Z999', 'CT-99');
        $this->post(route('app.assets.link', [$o, $a->uuid]), ['revision' => 3, 'assertion_id' => $foreign->id, 'confirmed' => 1])->assertNotFound();
        $this->post(route('app.assets.document', [$o, $a->uuid]), ['revision' => 3, 'version_id' => $foreignV->id])->assertNotFound();
        $this->assertDatabaseCount('asset_evidence_links', 1);
        $this->post(route('app.assets.document', [$o, $a->uuid]), ['revision' => 3, 'version_id' => $v->id])->assertRedirect();
        $this->assertDatabaseCount('asset_document_links', 1);
    }

    public function test_matching_does_not_assume_manufacturer_and_model_identify_a_unique_object(): void
    {
        [$u,$o,$p,$b] = $this->fixture();
        $this->create($u, $p, ['manufacturer' => 'Atlas', 'model' => 'SameModel', 'building_id' => $b->id]);
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('manual.png', 20, 20)], 'manual')[0]['version'];
        $fact = app(Engine::class)->propose($u, $b, ['semantic_key' => 'equipment.manufacturer', 'subject_type' => 'equipment', 'subject_reference' => 'UNKNOWN-UNIT', 'value' => 'Atlas', 'quote' => 'Manufacturer Atlas'], $v);
        $response = $this->get(route('app.assets.import', [$o, 'candidate' => $fact->id]))->assertOk();
        $this->assertCount(0, $response->viewData('matches'));
        Http::assertNothingSent();
    }

    public function test_invalid_custom_attributes_and_cross_property_served_spaces_are_atomic(): void
    {
        [$u,$o,$p] = $this->fixture();
        $foreign = $o->properties()->create(['name' => 'Other', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $space = $foreign->spaces()->create(['name' => 'Foreign room', 'space_type' => 'room']);
        foreach ([['attributes' => ['power_kw' => 'not-a-number']], ['attributes' => ['unregistered' => 'value']], ['served_spaces' => [$space->id]]] as $bad) {
            try {
                $this->create($u, $p, $bad);
                $this->fail('Invalid asset payload');
            } catch (ValidationException) {
                $this->assertDatabaseCount('assets', 0);
                $this->assertDatabaseCount('asset_audits', 0);
            }
        }
    }

    public function test_serials_keep_leading_zeroes_and_zero_is_not_a_missing_identifier(): void
    {
        [$u,$o,$p,$b] = $this->fixture();
        $a = $this->create($u, $p, ['serial_number' => '00123']);
        [$fact] = $this->source($u, $b, '123');
        app(Engine::class)->review($u, $b, $fact->id, 1, 'confirm');
        $link = app(EvidenceBridge::class)->link($u, $a, $fact, 1);
        try {
            app(EvidenceBridge::class)->apply($u, $a, $link, 2, 2, 'Checked serial');
            $this->fail('Numeric serial comparison bypassed replacement');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('replace', $e->errors());
        }
        $this->create($u, $p, ['serial_number' => '0']);
        try {
            $this->create($u, $p, ['serial_number' => '0']);
            $this->fail('Zero serial treated as missing');
        } catch (ValidationException) {
            $this->assertSame(2, Asset::count());
        }
        [$new] = $this->source($u, $b, '00123');
        $this->assertSame(1, app(Engine::class)->conflicts($new)->count());
        $this->assertTrue(Facts::same(15.0,'15','number'));
        $this->assertSame('00123',$a->fresh()->manual_data['serial_number']);
    }
}
