<?php

namespace Tests\Feature;

use App\Assets\Registry;
use App\Dna\Graph;
use App\Dna\Impact;
use App\Dna\Insights;
use App\Dna\Relations;
use App\Dna\Settings;
use App\Dna\Sources;
use App\Dna\Types;
use App\Evidence\Engine;
use App\Field\Invitations;
use App\Field\QrRegistry;
use App\Maintenance\Workflow;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\DnaRelation;
use App\Models\Floor;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Support\BuildingDeclarationStudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuildingDnaTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $type = 'hotel'): array
    {
        Http::preventStrayRequests();
        Storage::fake('evidence');
        $u = User::factory()->create(['locale' => 'en', 'profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $o = Organization::create(['name' => 'Atlas DNA']);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $o->memberships()->create(['user_id' => $u->id, 'role' => 'owner']);
        $p = $o->properties()->create(['name' => 'DNA '.$type, 'property_type' => $type, 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b = $p->buildings()->create(['name' => 'Pavilion North', 'state' => 'active']);
        $f = $b->floors()->create(['name' => 'Level 1', 'position' => 1]);
        $s = $p->spaces()->create(['name' => 'Room 101', 'space_type' => 'room', 'building_id' => $b->id, 'floor_id' => $f->id]);
        $a = $this->asset($u, $p, 'Boiler CT-001', ['is_system' => true, 'building_id' => $b->id]);
        $c = $this->asset($u, $p, 'Heating Circuit A', ['is_system' => true, 'space_id' => $s->id]);
        $this->login($u);

        return [$u, $o, $p, $b, $f, $s, $a, $c];
    }

    private function login(User $u): void
    {
        $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_locale' => 'en']);
    }

    private function asset(User $u, Property $p, string $name, array $extra = []): Asset
    {
        return app(Registry::class)->save($u, $p, ['name' => $name, 'asset_type_id' => AssetType::where('key', 'hvac')->value('id'), 'status' => 'unknown', ...$extra]);
    }

    private function relate(User $u, Property $p, string $source, string $target, string $type = 'supplies', array $extra = []): DnaRelation
    {
        return app(Relations::class)->create($u, $p, ['source' => $source, 'target' => $target, 'type_key' => $type, 'declaration' => 'Operator recorded this connection.', 'proposed' => false, ...$extra]);
    }

    private function key($model): string
    {
        return ($model instanceof Asset ? 'asset' : ($model instanceof Space ? 'space' : ($model instanceof Building ? 'building' : ($model instanceof Floor ? 'floor' : 'property')))).':'.$model->uuid;
    }

    public function test_existing_structure_is_adapted_with_stable_uuid_keys_and_accessible_ui(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $g = app(Graph::class);
        $nodes = $g->nodes($p)->get();
        $this->assertCount(6, $nodes);
        $this->assertNotNull($p->uuid);
        $this->assertDatabaseCount('dna_relations', 0);
        $page = $this->get(route('app.dna.index', [$o, 'property' => $p->id]))->assertOk()->assertSee('Accessible list')->assertSee('Building DNA');
        $this->assertSame('gnosix.dna.v1', $page->viewData('graph')['contract']);
        $this->get(route('app.dna.graph', [$o, $p, 'focus' => $this->key($a)]))->assertOk()->assertJsonPath('focus.kind', 'system');
        $this->assertDatabaseCount('dna_relations', 0);
    }

    public function test_serves_uses_original_pivot_and_registry_edit_is_visible_without_copy(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $r = $this->relate($u, $p, $this->key($c), $this->key($s), 'serves');
        $this->assertSame('adapter', $r->backing);
        $this->assertDatabaseHas('asset_served_spaces', ['asset_id' => $c->id, 'space_id' => $s->id]);
        $this->assertSame(1, app(Graph::class)->edges($p)->where('type_key', 'serves')->count());
        $c->servedSpaces()->detach($s->id);
        $this->assertSame(0, app(Graph::class)->edges($p)->where('type_key', 'serves')->count());
        $c->servedSpaces()->attach($s->id);
        $this->assertSame($r->uuid, app(Graph::class)->edges($p)->where('type_key', 'serves')->value('edge_key'));
    }

    public function test_hypothetical_failure_returns_explainable_paths_without_operational_mutations(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $this->relate($u, $p, $this->key($a), $this->key($c));
        $this->relate($u, $p, $this->key($c), $this->key($s), 'serves');
        $before = DB::table('spaces')->get()->toJson();
        $result = app(Impact::class)->run($u, $p, $this->key($a));
        $this->assertTrue($result['simulation']);
        $this->assertCount(1, $result['potential_spaces']);
        $this->assertCount(2, $result['paths'][$this->key($s)]);
        $this->assertTrue($result['paths'][$this->key($s)][0]['unverified']);
        $this->assertSame($before, DB::table('spaces')->get()->toJson());
        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_single_cabin_and_guesthouse_use_the_same_domain_without_room_assumptions(): void
    {
        foreach (['cabin', 'guesthouse'] as $kind) {
            [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture($kind);
            $this->relate($u, $p, $this->key($a), $this->key($s), 'serves');
            $r = app(Impact::class)->run($u, $p, $this->key($a));
            $this->assertCount(1, $r['potential_spaces']);
            $this->assertNull($b->construction_year);
            $this->assertSame($kind, $p->property_type);
        }
    }

    public function test_hotel_several_circuits_and_hostel_shared_bathroom_have_distinct_coverage(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture('hostel');
        $bath = $p->spaces()->create(['name' => 'Shared bathroom', 'space_type' => 'common_area', 'building_id' => $b->id, 'floor_id' => $f->id]);
        $dorm = $p->spaces()->create(['name' => 'Dormitory', 'space_type' => 'dormitory', 'building_id' => $b->id, 'floor_id' => $f->id, 'bed_count' => 8]);
        $c2 = $this->asset($u, $p, 'HVAC B', ['is_system' => true, 'building_id' => $b->id]);
        $this->relate($u, $p, $this->key($a), $this->key($c));
        $this->relate($u, $p, $this->key($a), $this->key($c2));
        foreach ([$s, $bath] as $room) {
            $this->relate($u, $p, $this->key($c), $this->key($room), 'serves');
        }$this->relate($u, $p, $this->key($c2), $this->key($dorm), 'serves');
        $this->assertCount(3, app(Impact::class)->run($u, $p, $this->key($a))['potential_spaces']);
        $this->assertCount(1, app(Impact::class)->run($u, $p, $this->key($c2))['potential_spaces']);
        $this->assertSame(8, $dorm->bed_count);
    }

    public function test_resort_shared_service_crosses_three_buildings_within_one_property(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture('resort');
        $rooms = [$s];
        foreach (['South', 'West'] as $name) {
            $building = $p->buildings()->create(['name' => $name, 'state' => 'active']);
            $rooms[] = $p->spaces()->create(['name' => 'Room '.$name, 'space_type' => 'room', 'building_id' => $building->id]);
        }
        foreach ($rooms as $room) {
            $this->relate($u, $p, $this->key($a), $this->key($room), 'serves');
        }$result = app(Impact::class)->run($u, $p, $this->key($a));
        $this->assertCount(3, $result['potential_spaces']);
        $this->assertSame(3, DB::table('asset_served_spaces')->count());
    }

    public function test_redundant_routes_do_not_claim_unavailability(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $backup = $this->asset($u, $p, 'Backup boiler', ['is_system' => true, 'building_id' => $b->id]);
        $this->relate($u, $p, $this->key($a), $this->key($c));
        $this->relate($u, $p, $this->key($backup), $this->key($c));
        $this->relate($u, $p, $this->key($c), $this->key($s), 'serves');
        $result = app(Impact::class)->run($u, $p, $this->key($a));
        $this->assertContains($this->key($c), $result['redundant_routes']);
        $this->assertFalse($result['coverage_certified']);
        $this->assertTrue($result['unknown_coverage']);
        $this->assertDatabaseHas('spaces', ['id' => $s->id, 'name' => 'Room 101']);
    }

    public function test_generic_connectivity_and_system_membership_do_not_propagate_failure(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $c->update(['system_id' => $a->id]);
        $this->relate($u, $p, $this->key($c), $this->key($s), 'serves');
        $this->relate($u, $p, $this->key($a), $this->key($c), 'connected_to');
        $result = app(Impact::class)->run($u, $p, $this->key($a));
        $this->assertCount(0, $result['potential_spaces']);
        $this->assertNotEmpty($result['connections']);
        $this->assertSame(0, count($result['potential_equipment']));
    }

    public function test_dependency_directions_and_cycles_are_validated_across_semantic_types(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $this->relate($u, $p, $this->key($c), $this->key($a), 'powered_by');
        $this->relate($u, $p, $this->key($c), $this->key($s), 'serves');
        $this->assertCount(1, app(Impact::class)->run($u, $p, $this->key($a))['potential_spaces']);
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $this->key($c), 'target' => $this->key($a), 'type_key' => 'feeds', 'declaration' => 'Invalid circular supply', 'proposed' => 0])->assertSessionHasErrors('relation');
        $this->assertDatabaseCount('dna_relations', 2);
    }

    public function test_duplicate_and_invalid_endpoint_relations_are_rejected(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $r = $this->relate($u, $p, $this->key($a), $this->key($c), 'connected_to');
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $r->target, 'target' => $r->source, 'type_key' => 'connected_to', 'declaration' => 'Reverse duplicate', 'proposed' => 0])->assertSessionHasErrors('relation');
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $this->key($b), 'target' => $this->key($c), 'type_key' => 'supplies', 'declaration' => 'Not physical equipment', 'proposed' => 0])->assertStatus(422);
        $this->assertDatabaseCount('dna_relations', 1);
    }

    public function test_cross_tenant_properties_nodes_sources_and_traversal_are_denied(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        [$other,$o2,$p2,$b2,$f2,$s2,$a2] = $this->fixture();
        $this->login($u);
        $this->get(route('app.dna.graph', [$o, $p2]))->assertNotFound();
        $this->get(route('app.dna.graph', [$o2, $p2]))->assertForbidden();
        $this->get(route('app.dna.simulate', [$o, $p, 'start' => $this->key($a2)]))->assertNotFound();
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $this->key($a), 'target' => $this->key($a2), 'type_key' => 'supplies', 'declaration' => 'Cross tenant', 'proposed' => 0])->assertNotFound();
        $this->assertDatabaseCount('dna_relations', 0);
    }

    public function test_property_scoped_member_and_field_invitation_do_not_grant_hidden_graph_access(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $tech = User::factory()->create();
        $o->memberships()->create(['user_id' => $tech->id, 'role' => 'technician', 'access_scope' => 'properties']);
        $this->login($tech);
        $this->get(route('app.dna.graph', [$o, $p]))->assertForbidden();
        $o->memberships()->where('user_id', $tech->id)->delete();
        $work = app(Workflow::class)->work($u, $p, ['title' => 'Scoped field', 'category' => 'cooling', 'priority' => 'normal', 'asset_id' => $a->id]);
        [$invite,$token] = app(Invitations::class)->create($u, $p, ['work_ids' => [$work->id], 'expires_at' => now($p->timezone)->addDay()->format('Y-m-d\TH:i'), 'allow_costs' => false]);
        app(Invitations::class)->redeem($tech, $token);
        $this->get(route('field.work', $work->uuid))->assertOk();
        $this->get(route('app.dna.graph', [$o, $p]))->assertForbidden();
    }

    public function test_disabled_capabilities_and_viewer_mutations_are_denied_server_side(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $viewer = User::factory()->create();
        $o->memberships()->create(['user_id' => $viewer->id, 'role' => 'member']);
        $this->login($viewer);
        $this->get(route('app.dna.graph', [$o, $p]))->assertOk();
        $this->post(route('app.dna.store', [$o, $p]), [])->assertForbidden();
        $this->login($u);
        $o->capabilityOverrides()->create(['capability' => 'hospitality.rooms', 'enabled' => false]);
        $this->get(route('app.dna.graph', [$o, $p]))->assertForbidden();
    }

    public function test_archive_restore_and_permanent_purge_preserve_graph_history(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $r = $this->relate($u, $p, $this->key($a), $this->key($c));
        $this->relate($u, $p, $this->key($c), $this->key($s), 'serves');
        $registry = app(Registry::class);
        $registry->archive($u, $c, $c->fresh()->revision, true);
        $this->assertCount(0, app(Impact::class)->run($u, $p, $this->key($a))['potential_spaces']);
        $registry->archive($u, $c->fresh(), $c->fresh()->revision, false);
        $this->assertCount(1, app(Impact::class)->run($u, $p, $this->key($a))['potential_spaces']);
        $uuid = $r->uuid;
        $b->delete();
        $this->get(route('app.dna.graph', [$o, $p, 'focus' => $this->key($a)]))->assertNotFound();
        $b->restore();
        $this->get(route('app.dna.graph', [$o, $p, 'focus' => $this->key($a)]))->assertOk();
        $b->delete();
        app(BuildingDeclarationStudio::class)->purge($u, $p, $b, $b->fresh()->declaration_version);
        $this->assertDatabaseHas('dna_relations', ['uuid' => $uuid]);
        $this->get(route('app.dna.index', [$o, 'property' => $p->id, 'relation' => $uuid]))->assertOk()->assertSee('Unavailable / archived node');
    }

    public function test_evidence_supported_relationship_is_proposed_reviewed_and_flags_superseded_document(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $engine = app(Engine::class);
        $v = $engine->uploads($u, $b, [UploadedFile::fake()->image('heating-plan.png', 60, 60)], 'plan')[0]['version'];
        $r = $this->relate($u, $p, $this->key($a), $this->key($c), 'supplies', ['sources' => [['version_id' => $v->id, 'page' => 1]]]);
        $this->assertSame('proposed', $r->state);
        $this->assertCount(0, app(Impact::class)->run($u, $p, $this->key($a))['potential_equipment']);
        $this->post(route('app.dna.review', [$o, $p, $r->uuid]), ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Plan reviewed by operator.'])->assertRedirect();
        $this->assertSame('confirmed', $r->fresh()->state);
        $this->assertCount(2, $r->revisions()->get());
        $this->get(route('app.dna.relation', [$o, $p, 'key' => $r->uuid]))->assertOk()->assertJsonPath('evidence.sources.0.page', 1)->assertJsonPath('evidence.sources.0.current', true)->assertJsonPath('author', $u->name)->assertJsonPath('reviewer', $u->name);
        $engine->uploads($u, $b, [UploadedFile::fake()->image('heating-plan-new.png', 70, 70)], 'plan', $v->document_id);
        $detail = $this->get(route('app.dna.relation', [$o, $p, 'key' => $r->uuid]))->assertOk();
        $detail->assertJsonPath('evidence.review_required', true);
        $this->assertSame('confirmed', $r->fresh()->state);
        $this->assertTrue(app(Impact::class)->run($u, $p, $this->key($a))['paths'][$this->key($c)][0]['unverified']);
    }

    public function test_evidence_assertions_keep_human_review_and_ai_confidence_separate(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $engine = app(Engine::class);
        $fact = $engine->propose($u, $b, ['semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => $a->reference, 'value' => 'CT001']);
        $r = $this->relate($u, $p, $this->key($a), $this->key($c), 'feeds', ['sources' => [['assertion_id' => $fact->id]]]);
        $this->post(route('app.dna.review', [$o, $p, $r->uuid]), ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Not yet verified'])->assertSessionHasErrors('sources');
        $engine->review($u, $b, $fact->id, 1, 'confirm', 'Serial source checked');
        $this->post(route('app.dna.review', [$o, $p, $r->uuid]), ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Current fact reviewed'])->assertRedirect();
        $this->assertSame('confirmed', $r->fresh()->state);
        $this->assertSame($fact->fresh()->revision, $r->fresh()->review_basis[$fact->id]);
        $this->assertSame(1, $r->sources()->sole()->source_revision);
        $this->assertFalse(app(Sources::class)->health($p, [$r->uuid])[$r->uuid]['review_required']);
        $this->assertNull($fact->fresh()->ai_confidence);
        $this->get(route('app.dna.relation', [$o, $p, 'key' => $r->uuid]))->assertJsonPath('evidence.sources.0.ai_confidence', null);
        $engine->propose($u, $b, ['semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => $a->reference, 'value' => 'CT002']);
        $this->assertTrue(app(Sources::class)->health($p, [$r->uuid])[$r->uuid]['review_required']);
        $this->assertSame('confirmed', $r->fresh()->state);
        $this->assertSame('CT001', $fact->fresh()->original_value);
    }

    public function test_confirmed_relations_need_explicit_dispute_and_contradiction_review(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $r = $this->relate($u, $p, $this->key($a), $this->key($c));
        $service = app(Relations::class);
        $service->review($u, $r, ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Reviewed']);
        $this->patch(route('app.dna.update', [$o, $p, $r->uuid]), ['revision' => 2, 'declaration' => 'Silent overwrite'])->assertSessionHasErrors('relation');
        $replacement = $this->relate($u, $p, $this->key($a), $this->key($s), 'serves', ['contradicts' => $r->uuid]);
        $this->post(route('app.dna.review', [$o, $p, $replacement->uuid]), ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Conflict unresolved'])->assertSessionHasErrors('relation');
        $service->review($u, $r->fresh(), ['revision' => 2, 'state' => 'superseded', 'reason' => 'Incorrect connection replaced explicitly']);
        $service->review($u, $replacement, ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Replacement checked']);
        $this->assertSame('confirmed', $replacement->fresh()->state);
        $this->assertSame('adapter', $replacement->fresh()->backing);
        $this->assertDatabaseHas('asset_served_spaces', ['asset_id' => $a->id, 'space_id' => $s->id]);
        $this->assertSame('Operator recorded this connection.', $r->fresh()->declaration);
    }

    public function test_review_and_edit_use_revision_guards_and_append_only_snapshots(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $r = $this->relate($u, $p, $this->key($a), $this->key($c));
        $service = app(Relations::class);
        $service->update($u, $r, ['revision' => 1, 'declaration' => 'Reviewed pipe route']);
        $this->post(route('app.dna.review', [$o, $p, $r->uuid]), ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Stale review'])->assertSessionHasErrors('revision');
        $this->assertSame(2, $r->revisions()->count());
        $this->assertSame('Operator recorded this connection.', $r->revisions()->oldest('revision')->value('snapshot')['declaration']);
        $this->assertSame('declared', $r->fresh()->state);
    }

    public function test_custom_semantics_cardinality_and_connectivity_safety_are_enforced(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $data = ['name' => 'Dedicated circuit', 'source' => ['asset', 'system'], 'target' => ['asset', 'system'], 'category' => 'dependency', 'direction' => 'forward', 'impact' => 1, 'cycles' => 0, 'max_source' => 1, 'max_target' => 0];
        $type = app(Types::class)->create($u, $p, $data);
        $key = 'custom_'.$type->uuid;
        $this->relate($u, $p, $this->key($a), $this->key($c), $key);
        $third = $this->asset($u, $p, 'Third equipment');
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $this->key($a), 'target' => $this->key($third), 'type_key' => $key, 'declaration' => 'Too many dedicated branches', 'proposed' => 0])->assertSessionHasErrors('relation');
        $this->post(route('app.dna.type', [$o, $p]), [...$data, 'category' => 'connectivity'])->assertStatus(422);
    }

    public function test_insight_decisions_reference_sources_and_do_not_change_physical_facts(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $scan = app(Insights::class)->scan($u, $p);
        $observation = collect($scan['items'])->first(fn ($i) => $i['code'] === 'no_coverage' && $i['subject'] === $this->key($a));
        $this->assertNotNull($observation);
        $this->post(route('app.dna.insight', [$o, $p]), ['subject' => $observation['subject'], 'fingerprint' => $observation['fingerprint'], 'state' => 'dismissed', 'reason' => 'Served spaces not surveyed yet', 'revision' => 0])->assertRedirect();
        $this->assertDatabaseHas('dna_insight_decisions', ['fingerprint' => $observation['fingerprint'], 'state' => 'dismissed']);
        $this->assertDatabaseCount('asset_served_spaces', 0);
        $again = app(Insights::class)->scan($u, $p);
        $this->assertSame('dismissed', collect($again['items'])->firstWhere('fingerprint', $observation['fingerprint'])['state']);
        $this->post(route('app.dna.insight', [$o, $p]), ['subject' => $observation['subject'], 'fingerprint' => $observation['fingerprint'], 'state' => 'resolved', 'reason' => 'Stale', 'revision' => 0])->assertSessionHasErrors('revision');
    }

    public function test_critical_maintenance_and_qr_integrations_preserve_existing_workflows(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $qr = app(QrRegistry::class)->generate($u, $p, 'asset', $a->uuid);
        $locator = $qr->active()->locator;
        $w = app(Workflow::class)->work($u, $p, ['title' => 'Critical boiler issue', 'category' => 'hot_water', 'priority' => 'critical', 'asset_id' => $a->id]);
        $this->relate($u, $p, $this->key($a), $this->key($s), 'serves');
        $scan = app(Insights::class)->scan($u, $p);
        $insight = collect($scan['items'])->firstWhere('code', 'critical_work');
        $this->assertSame($w->uuid, $insight['references'][1]['uuid']);
        $this->assertSame($locator, $qr->active()->locator);
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertOk()->assertSee('Simulate equipment failure');
        $this->get(route('app.dna.simulate', [$o, $p, 'start' => $this->key($a)]))->assertOk();
        $this->assertSame('reported', $w->fresh()->state);
    }

    public function test_large_graph_neighborhood_and_impact_have_bounded_nodes_queries_and_depth(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $rows = [];
        for ($i = 0; $i < 10000; $i++) {
            $rows[] = ['uuid' => (string) Str::uuid(), 'reference' => 'DNA-BULK-'.$i, 'organization_id' => $o->id, 'property_id' => $p->id, 'asset_type_id' => $a->asset_type_id, 'name' => 'Bulk '.$i, 'status' => 'unknown', 'manual_data' => '{}', 'revision' => 1, 'is_system' => false, 'building_id' => $b->id, 'created_at' => now(), 'updated_at' => now()];
        }foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('assets')->insert($chunk);
        }
        $edges = [];
        foreach (array_slice($rows, 0, 2500) as $item) {
            $edges[] = ['uuid' => (string) Str::uuid(), 'organization_id' => $o->id, 'property_id' => $p->id, 'type_key' => 'supplies', 'source' => $this->key($a), 'target' => 'asset:'.$item['uuid'], 'backing' => 'new', 'state' => 'declared', 'declaration' => 'Performance fixture', 'revision' => 1, 'created_at' => now(), 'updated_at' => now()];
        }foreach (array_chunk($edges, 100) as $chunk) {
            DB::table('dna_relations')->insert($chunk);
        }
        $this->get(route('app.dna.graph', [$o, $p, 'focus' => $this->key($a)]))->assertOk()->assertJsonCount(61, 'nodes')->assertJsonPath('more', true);
        $this->get(route('app.dna.search', [$o, $p, 'q' => 'Bulk']))->assertOk()->assertJsonCount(20, 'items');
        DB::enableQueryLog();
        $result = app(Impact::class)->run($u, $p, $this->key($a), 2, 40);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(40, count($result['nodes']));
        $this->assertTrue($result['truncated']);
        $this->assertLessThan(160, $queries);
        fwrite(STDERR, "\nDNA dataset 10,000 nodes / 2,500 edges: ".$result['elapsed_ms']."ms impact, $queries queries\n");
    }

    public function test_document_sources_are_redacted_when_evidence_capabilities_are_missing(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('secret-plan.png')], 'manual')[0]['version'];
        $r = $this->relate($u, $p, $this->key($a), $this->key($c), 'supplies', ['sources' => [['version_id' => $v->id, 'page' => 1]]]);
        $tech = User::factory()->create();
        $o->memberships()->create(['user_id' => $tech->id, 'role' => 'technician']);
        $this->login($tech);
        $this->get(route('app.dna.relation', [$o, $p, 'key' => $r->uuid]))->assertOk()->assertJsonPath('evidence.restricted', true)->assertDontSee('secret-plan.png')->assertDontSee($v->id);
        $this->get(route('app.dna.sources', [$o, $p, 'kind' => 'version']))->assertForbidden();
        $this->post(route('app.dna.review', [$o, $p, $r->uuid]), ['revision' => 1, 'state' => 'confirmed', 'reason' => 'No permission'])->assertForbidden();
    }

    public function test_cross_property_evidence_cannot_be_attached_even_for_an_owner_of_both_properties(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $p2 = $o->properties()->create(['name' => 'Other owned property', 'property_type' => 'cabin', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b2 = $p2->buildings()->create(['name' => 'Other building', 'state' => 'active']);
        $v = app(Engine::class)->uploads($u, $b2, [UploadedFile::fake()->image('other-plan.png')], 'manual')[0]['version'];
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $this->key($a), 'target' => $this->key($c), 'type_key' => 'supplies', 'declaration' => 'Bad source scope', 'proposed' => 1, 'sources' => [['version_id' => $v->id]]])->assertNotFound();
        $this->assertDatabaseCount('dna_relations', 0);
    }

    public function test_archived_and_purged_sources_preserve_provenance_and_require_review(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('plan-history.png')], 'manual')[0]['version'];
        $r = $this->relate($u, $p, $this->key($a), $this->key($c), 'supplies', ['sources' => [['version_id' => $v->id]]]);
        app(Relations::class)->review($u, $r, ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Document reviewed']);
        $v->document->update(['archived_at' => now()]);
        $h = app(Sources::class)->health($p, [$r->uuid])[$r->uuid];
        $this->assertTrue($h['review_required']);
        $this->assertSame($v->id, $r->sources()->sole()->source_uuid);
        $b->delete();
        app(BuildingDeclarationStudio::class)->purge($u, $p, $b, $b->fresh()->declaration_version);
        $this->assertNull($r->sources()->sole()->version_id);
        $this->assertSame($v->id, $r->sources()->sole()->source_uuid);
        $this->assertSame(2, $r->revisions()->count());
    }

    public function test_circular_proposals_can_be_saved_but_cannot_be_confirmed_and_valid_connectivity_cycles_remain_allowed(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $this->relate($u, $p, $this->key($a), $this->key($c));
        $r = $this->relate($u, $p, $this->key($c), $this->key($a), 'feeds', ['proposed' => true]);
        $this->post(route('app.dna.review', [$o, $p, $r->uuid]), ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Circular proposal'])->assertSessionHasErrors('relation');
        $this->assertSame('proposed', $r->fresh()->state);
        $type = app(Types::class)->create($u, $p, ['name' => 'Cable link', 'source' => ['system'], 'target' => ['system'], 'category' => 'connectivity', 'direction' => 'forward', 'impact' => 0, 'cycles' => 1, 'max_source' => 0, 'max_target' => 0]);
        $this->relate($u, $p, $this->key($a), $this->key($c), 'custom_'.$type->uuid);
        $this->relate($u, $p, $this->key($c), $this->key($a), 'custom_'.$type->uuid);
        $this->assertDatabaseCount('dna_relations', 4);
    }

    public function test_serves_withdrawal_reproposal_and_original_registry_changes_do_not_create_parallel_truth(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $r = $this->relate($u, $p, $this->key($c), $this->key($s), 'serves');
        $service = app(Relations::class);
        $service->review($u, $r, ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Coverage checked']);
        $c->servedSpaces()->detach();
        $this->assertSame(0, app(Graph::class)->edges($p)->where('type_key', 'serves')->count());
        $this->assertTrue(app(Sources::class)->health($p, [$r->uuid])[$r->uuid]['review_required']);
        $c->servedSpaces()->attach($s->id);
        $service->review($u, $r->fresh(), ['revision' => 2, 'state' => 'superseded', 'reason' => 'Coverage removed']);
        $this->assertDatabaseCount('asset_served_spaces', 0);
        $service->review($u, $r->fresh(), ['revision' => 3, 'state' => 'proposed', 'reason' => 'Restore after checking']);
        $service->review($u, $r->fresh(), ['revision' => 4, 'state' => 'confirmed', 'reason' => 'Restoration reviewed']);
        $this->assertDatabaseCount('asset_served_spaces', 1);
        $this->assertSame(5, $r->revisions()->count());
    }

    public function test_traversal_depth_and_admin_budgets_are_enforced_without_new_environment_variables(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $third = $this->asset($u, $p, 'Circuit B', ['is_system' => true]);
        $this->relate($u, $p, $this->key($a), $this->key($c));
        $this->relate($u, $p, $this->key($c), $this->key($third));
        $this->relate($u, $p, $this->key($third), $this->key($s), 'serves');
        $result = app(Impact::class)->run($u, $p, $this->key($a), 1);
        $this->assertTrue($result['truncated']);
        $this->assertCount(0, $result['potential_spaces']);
        $this->put(route('admin.dna.update'), ['max_depth' => 2, 'max_nodes' => 40, 'timeout_ms' => 500])->assertRedirect();
        $this->assertDatabaseMissing('platform_settings', ['key' => 'dna']);
        $admin = PlatformAdmin::create(['name' => 'DNA admin', 'email' => 'dna-admin@example.test', 'password' => 'Dna-admin-2026!']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_confirmed_at' => time()]);
        $this->put(route('admin.dna.update'), ['max_depth' => 2, 'max_nodes' => 40, 'timeout_ms' => 500])->assertRedirect();
        $this->assertSame(2, app(Settings::class)->all()['max_depth']);
        $this->get(route('admin.settings', ['tab' => 'dna']))->assertOk()->assertSee('Limitele explorării');
        $this->withSession(['platform_admin_confirmed_at' => 0])->putJson(route('admin.dna.update'), ['max_depth' => 3, 'max_nodes' => 80, 'timeout_ms' => 900])->assertStatus(423);
    }

    public function test_source_page_links_initialize_existing_evidence_viewer_and_validate_bounds(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('page-source.png')], 'manual')[0]['version'];
        $this->get(route('app.evidence.show', [$o, $p, $b->uuid, 'version' => $v->id, 'page' => 2]))->assertOk()->assertViewHas('initialPage', 2);
        $this->get(route('app.evidence.show', [$o, $p, $b->uuid, 'version' => $v->id, 'page' => 301]))->assertSessionHasErrors('page');
    }

    public function test_building_entry_scopes_observations_and_preserves_cross_building_graph_exploration(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $b2 = $p->buildings()->create(['name' => 'South building', 'state' => 'active']);
        $outside = $this->asset($u, $p, 'South equipment', ['building_id' => $b2->id]);
        $r = $this->relate($u, $p, $this->key($a), $this->key($outside));
        $view = $this->get(route('app.dna.index', [$o, 'property' => $p->id, 'focus' => $this->key($b)]))->assertOk()->assertViewHas('insightBuilding', $this->key($b));
        $subjects = array_column($view->viewData('insights')['items'], 'subject');
        $this->assertNotContains($this->key($outside), $subjects);
        $this->assertContains('relation:'.$r->uuid, $subjects);
        $this->get(route('app.dna.graph', [$o, $p, 'focus' => $this->key($a)]))->assertOk()->assertSee('South equipment');
    }

    public function test_acyclic_symmetric_custom_connections_cannot_close_an_undirected_cycle(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $third = $this->asset($u, $p, 'Third system', ['is_system' => true]);
        $type = app(Types::class)->create($u, $p, ['name' => 'Cable tree', 'source' => ['system'], 'target' => ['system'], 'category' => 'connectivity', 'direction' => 'both', 'impact' => 0, 'cycles' => 0, 'max_source' => 0, 'max_target' => 0]);
        $key = 'custom_'.$type->uuid;
        $initial = $this->relate($u, $p, $this->key($a), $this->key($c), $key);
        app(Relations::class)->review($u, $initial, ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Cable route checked']);
        $this->assertSame('confirmed', $initial->fresh()->state);
        $this->relate($u, $p, $this->key($c), $this->key($third), $key);
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $this->key($third), 'target' => $this->key($a), 'type_key' => $key, 'declaration' => 'Invalid cable loop', 'proposed' => 0])->assertSessionHasErrors('relation');
        $this->assertDatabaseCount('dna_relations', 2);
    }

    public function test_symmetric_relationships_preserve_distinct_typed_endpoint_roles(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $type = app(Types::class)->create($u, $p, ['name' => 'Building equipment connection', 'source' => ['building'], 'target' => ['system'], 'category' => 'connectivity', 'direction' => 'both', 'impact' => 0, 'cycles' => 1, 'max_source' => 0, 'max_target' => 0]);
        $r = $this->relate($u, $p, $this->key($b), $this->key($a), 'custom_'.$type->uuid);
        $this->assertSame($this->key($b), $r->source);
        $this->assertSame($this->key($a), $r->target);
        app(Relations::class)->review($u, $r, ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Typed roles checked']);
        $this->assertSame('confirmed', $r->fresh()->state);
    }

    public function test_changes_to_system_classification_flag_typed_relationships_without_rewriting_history(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $type = app(Types::class)->create($u, $p, ['name' => 'Systems supply', 'source' => ['system'], 'target' => ['system'], 'category' => 'dependency', 'direction' => 'forward', 'impact' => 1, 'cycles' => 0, 'max_source' => 0, 'max_target' => 0]);
        $r = $this->relate($u, $p, $this->key($a), $this->key($c), 'custom_'.$type->uuid);
        app(Relations::class)->review($u, $r, ['revision' => 1, 'state' => 'confirmed', 'reason' => 'Systems checked']);
        $a->update(['is_system' => false]);
        $h = app(Sources::class)->health($p, [$r->uuid])[$r->uuid];
        $this->assertTrue($h['review_required']);
        $this->assertSame('confirmed', $r->fresh()->state);
        $this->assertSame(2, $r->revisions()->count());
        $this->assertTrue(app(Impact::class)->run($u, $p, $this->key($a))['paths'][$this->key($c)][0]['unverified']);
    }

    public function test_property_editor_can_manage_links_but_cannot_define_organization_wide_semantics(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$c] = $this->fixture();
        $editor = User::factory()->create();
        $m = $o->memberships()->create(['user_id' => $editor->id, 'role' => 'member', 'access_scope' => 'properties']);
        $m->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'editor']);
        $this->login($editor);
        $this->post(route('app.dna.store', [$o, $p]), ['source' => $this->key($a), 'target' => $this->key($c), 'type_key' => 'supplies', 'declaration' => 'Authorized property declaration', 'proposed' => 0])->assertRedirect();
        $this->assertDatabaseCount('dna_relations', 1);
        $this->post(route('app.dna.type', [$o, $p]), ['name' => 'Unauthorized organization type', 'source' => ['system'], 'target' => ['system'], 'category' => 'dependency', 'direction' => 'forward', 'impact' => 1, 'cycles' => 0, 'max_source' => 0, 'max_target' => 0])->assertForbidden();
        $this->assertDatabaseCount('dna_relation_types', 0);
    }

    public function test_property_identity_repairs_old_release_inserts_without_changing_business_data(): void
    {
        [$u,$o] = $this->fixture();
        $id = DB::table('properties')->insertGetId(['organization_id' => $o->id, 'name' => 'Legacy concurrent property', 'property_type' => 'cabin', 'country' => 'RO', 'timezone' => 'Europe/Bucharest', 'amenities' => '[]', 'uuid' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertNull(DB::table('properties')->where('id', $id)->value('uuid'));
        $property = Property::findOrFail($id);
        $this->assertNotNull($property->uuid);
        $this->assertSame($property->uuid, Property::findOrFail($id)->uuid);
        $this->assertSame('Legacy concurrent property', $property->name);
        $this->assertFalse($property->isDirty('uuid'));
    }

    public function test_property_ownership_does_not_invent_a_physical_location_for_unplaced_equipment(): void
    {
        [$u,$o,$p] = $this->fixture();
        $unplaced = $this->asset($u, $p, 'Equipment without location');
        $edge = app(Graph::class)->edges($p)->where('source', $this->key($unplaced))->firstOrFail();
        $this->assertSame('registered_to', $edge->type_key);
        $this->assertSame($this->key($p), $edge->target);
        $this->assertNull($unplaced->building_id);
    }
}
