<?php

namespace Tests\Feature;

use App\Assets\Registry;
use App\Evidence\Engine;
use App\Evidence\Retention;
use App\Jobs\GenerateMaintenance;
use App\Jobs\NotifyMaintenance;
use App\Maintenance\Settings;
use App\Maintenance\Workflow;
use App\Models\AssetAudit;
use App\Models\AssetType;
use App\Models\MaintenanceIssue;
use App\Models\MaintenancePlan;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\MaintenanceAssigned;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $type = 'hotel'): array
    {
        Storage::fake('evidence');
        Http::preventStrayRequests();
        $u = User::factory()->create(['locale' => 'en', 'profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $o = Organization::create(['name' => 'Atlas Maintenance']);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $o->memberships()->create(['user_id' => $u->id, 'role' => 'owner']);
        $p = $o->properties()->create(['name' => 'Property '.$type, 'property_type' => $type, 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b = $p->buildings()->create(['name' => 'Pavilion', 'state' => 'active']);
        $f = $b->floors()->create(['name' => 'Ground', 'position' => 0]);
        $s = $p->spaces()->create(['name' => 'Room 101', 'building_id' => $b->id, 'floor_id' => $f->id, 'space_type' => 'room']);
        $a = app(Registry::class)->save($u, $p, ['name' => 'CT-01', 'asset_type_id' => AssetType::where('key', 'hvac')->value('id'), 'status' => 'unknown', 'space_id' => $s->id]);
        $this->login($u);

        return [$u, $o, $p, $b, $s, $a];
    }

    private function login(User $u): void
    {
        $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_locale' => 'en']);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['title' => 'Air conditioner not cooling', 'description' => 'Guest reports warm air.', 'category' => 'cooling', 'severity' => 'normal', 'impact' => 'discomfort', 'submit' => 1, 'priority' => 'normal'], $extra);
    }

    private function work($u, $p, $extra = []): WorkOrder
    {
        return app(Workflow::class)->work($u, $p, $this->data($extra));
    }

    private function issue($u, $p, $extra = []): MaintenanceIssue
    {
        return app(Workflow::class)->issue($u, $p, $this->data($extra));
    }

    private function transition($u, $w, $state): void
    {
        app(Workflow::class)->transition($u, $w->fresh(), ['revision' => $w->fresh()->revision, 'state' => $state, 'reason' => 'Operationally justified change']);
    }

    private function perform($u, $w, $extra = []): void
    {
        app(Workflow::class)->perform($u, $w->fresh(), ['revision' => $w->fresh()->revision, 'work_performed' => 'Filter inspected and cleaned.', 'checklist' => [], ...$extra]);
    }

    private function staff($o, $role = 'technician')
    {
        $u = User::factory()->create(['locale' => 'en', 'onboarding_completed_at' => now()]);
        $o->memberships()->create(['user_id' => $u->id, 'role' => $role]);

        return $u;
    }

    public function test_cabin_report_draft_submission_and_resumption_render(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture('cabin');
        $this->get(route('app.maintenance.issue.create', [$o, 'property' => $p->id, 'space_uuid' => $s->uuid]))->assertOk()->assertSee('Room 101');
        $this->post(route('app.maintenance.issue.store', $o), $this->data(['property_id' => $p->id, 'space_id' => $s->id, 'submit' => 0]))->assertRedirect();
        $i = MaintenanceIssue::sole();
        $uuid = $i->uuid;
        $this->assertSame('draft', $i->state);
        $this->get(route('app.maintenance.issue.edit', [$o, $uuid]))->assertOk();
        $this->patch(route('app.maintenance.issue.update', [$o, $uuid]), $this->data(['property_id' => $p->id, 'space_id' => $s->id, 'revision' => 1]))->assertRedirect();
        $this->assertSame('reported', $i->fresh()->state);
        $this->assertNotNull($i->fresh()->reported_at);
        $this->assertSame($uuid, $i->fresh()->uuid);
        $this->get(route('app.maintenance.issue.show', [$o, $uuid]))->assertOk()->assertSee('Create work order');
    }

    public function test_staff_reports_without_document_privileges_and_cannot_assign_or_verify(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $staff = $this->staff($o);
        $this->login($staff);
        $this->post(route('app.maintenance.issue.store', $o), $this->data(['property_id' => $p->id, 'asset_id' => $a->id]))->assertRedirect();
        $this->post(route('app.maintenance.work.store', $o), $this->data(['property_id' => $p->id]))->assertForbidden();
        $w = $this->work($u, $p, ['asset_id' => $a->id, 'assigned_to' => $staff->id]);
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'transition']), ['revision' => $w->revision, 'state' => 'verified', 'reason' => 'Try'])->assertForbidden();
        $o->capabilityOverrides()->create(['capability' => 'evidence.upload', 'enabled' => false]);
        $o->capabilityOverrides()->create(['capability' => 'evidence.review', 'enabled' => false]);
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertOk()->assertDontSee('Building Evidence Studio');
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'upload']), ['files' => [UploadedFile::fake()->image('completion.png')]])->assertRedirect();
        $this->assertDatabaseCount('evidence_versions', 1);
        $this->get(route('app.evidence.show', [$o, $p, $b->uuid]))->assertForbidden();
    }

    public function test_full_hotel_lifecycle_technician_manager_verification_and_private_notes(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $tech = $this->staff($o);
        $i = $this->issue($u, $p, ['asset_id' => $a->id]);
        $i2 = $this->issue($u, $p, ['asset_id' => $a->id]);
        $w = $this->work($u, $p, ['asset_id' => $a->id, 'assigned_to' => $tech->id, 'issue_ids' => [$i->id, $i2->id], 'checklist' => [['text' => 'Inspect filter', 'done' => false]]]);
        $this->assertCount(2, $w->issues);
        foreach (['triaged', 'assigned'] as $state) {
            $this->transition($u, $w, $state);
        }$this->transition($tech, $w, 'in_progress');
        $this->transition($tech, $w, 'awaiting_parts');
        $this->transition($tech, $w, 'in_progress');
        $this->perform($tech, $w, ['checklist' => [['text' => 'Inspect filter', 'done' => true]], 'labor_cost' => '25.50', 'currency' => 'RON', 'parts' => [['name' => 'Filter', 'quantity' => 1, 'cost' => 40]]]);
        $this->transition($tech, $w, 'resolved');
        $this->transition($u, $w, 'verified');
        $this->transition($u, $w, 'closed');
        $this->assertSame($u->id, $w->fresh()->verified_by);
        $this->assertNotNull($w->fresh()->closed_at);
        $this->assertSame('40.00', $w->fresh()->materials_cost);
        $this->assertNull($w->fresh()->external_cost);
        app(Workflow::class)->comment($u, $w->fresh(), ['body' => 'Private contract discussion', 'private' => 1]);
        $this->login($tech);
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertOk()->assertDontSee('Private contract discussion');
        $this->login($u);
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertSee('Private contract discussion');
        app(Workflow::class)->issueState($u, $i->fresh(), 1, 'closed', 'All work verified');
        app(Workflow::class)->issueState($u, $i->fresh(), 2, 'reported', 'Issue recurred');
        $this->assertNull($i->fresh()->closed_at);
        $this->transition($u, $w, 'in_progress');
        $this->assertNull($w->fresh()->verified_by);
        $this->assertNull($w->fresh()->resolved_at);
        $this->assertGreaterThan(10, AssetAudit::where('asset_id', $a->id)->count());
    }

    public function test_unassigned_and_cross_tenant_transitions_are_denied(): void
    {
        [$u,$o,$p] = $this->fixture();
        $w = $this->work($u, $p);
        $other = $this->staff($o);
        $this->login($other);
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'transition']), ['revision' => 1, 'state' => 'in_progress', 'reason' => 'Unauthorized'])->assertForbidden();
        $foreign = Organization::create(['name' => 'Other']);
        $foreign->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $foreign->memberships()->create(['user_id' => $other->id, 'role' => 'owner']);
        $this->get(route('app.maintenance.work.show', [$foreign, $w->uuid]))->assertNotFound();
        $this->login($u);
        $this->get(route('app.maintenance.index', $foreign))->assertForbidden();
    }

    public function test_property_grants_scope_dashboard_lookup_assignment_and_record_access(): void
    {
        [$u,$o,$p] = $this->fixture();
        $p2 = $o->properties()->create(['name' => 'Confidential property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $w = $this->work($u, $p2, ['title' => 'Restricted work']);
        $staff = $this->staff($o);
        $m = $o->memberships()->where('user_id', $staff->id)->first();
        $m->update(['access_scope' => 'properties']);
        $m->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'viewer']);
        $this->login($staff);
        $this->get(route('app.maintenance.index', $o))->assertOk()->assertDontSee('Restricted work');
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertForbidden();
        $this->getJson(route('app.maintenance.lookup', [$o, 'kind' => 'asset', 'property' => $p2->id]))->assertForbidden();
        $this->login($u);
        $this->post(route('app.maintenance.work.store', $o), $this->data(['property_id' => $p2->id, 'assigned_to' => $staff->id]))->assertStatus(422);
    }

    public function test_validation_and_optimistic_revision_do_not_lose_changes(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $w = $this->work($u, $p);
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'transition']), ['revision' => 999, 'state' => 'triaged', 'reason' => 'Stale'])->assertSessionHasErrors('revision');
        $this->assertSame('reported', $w->fresh()->state);
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'transition']), ['revision' => 1, 'state' => 'closed', 'reason' => 'Skip verification'])->assertSessionHasErrors();
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'transition']), ['revision' => 1, 'state' => 'assigned', 'reason' => 'No assignee'])->assertSessionHasErrors('state');
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'perform']), ['revision' => 1, 'labor_cost' => 10])->assertSessionHasErrors('currency');
        $this->assertNull($w->fresh()->labor_cost);
    }

    public function test_shared_hostel_equipment_and_multi_building_resort(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture('hostel');
        $b2 = $p->buildings()->create(['name' => 'Shared facilities', 'state' => 'active']);
        $s2 = $p->spaces()->create(['name' => 'Dormitory', 'building_id' => $b2->id, 'space_type' => 'dormitory']);
        $a->servedSpaces()->attach($s2);
        $w = $this->work($u, $p, ['asset_id' => $a->id, 'space_id' => $s2->id]);
        $this->assertSame($b2->id, $w->building_id);
        $this->assertSame($a->id, $w->asset_id);
        $this->get(route('app.maintenance.index', [$o, 'building' => $b->id]))->assertOk()->assertSee($w->reference);
        $a2 = app(Registry::class)->save($u, $p, ['name' => 'Other', 'asset_type_id' => $a->asset_type_id, 'status' => 'unknown', 'space_id' => $s->id]);
        $this->post(route('app.maintenance.issue.store', $o), $this->data(['property_id' => $p->id, 'space_id' => $s2->id, 'asset_id' => $a2->id]))->assertSessionHasErrors('asset_id');
    }

    public function test_repeated_failures_are_deterministic_with_references_and_category_window(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        for ($n = 0; $n < 3; $n++) {
            $w = $this->work($u, $p, ['asset_id' => $a->id]);
            $this->perform($u, $w);
            $this->transition($u, $w, 'resolved');
        }
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertOk()->assertSee('3 resolved interventions')->assertSee('not a verified root cause');
        $this->get(route('app.maintenance.index', $o))->assertOk()->assertSee('3 resolved interventions');
        $this->assertSame(3, app(Workflow::class)->repeats($w)->count());
        WorkOrder::whereKey($w->id)->update(['resolved_at' => now()->subDays(91)]);
        $this->assertSame(2, app(Workflow::class)->repeats($w)->count());
    }

    private function plan($u, $p, $extra = []): MaintenancePlan
    {
        return app(Workflow::class)->plan($u, $p, $this->data(['frequency' => 'quarterly', 'anchor_on' => now()->toDateString(), 'interval_count' => 1, 'interval_unit' => 'month', 'notify_assignee' => false, ...$extra]));
    }

    public function test_guesthouse_plan_generation_queue_retry_is_idempotent(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture('guesthouse');
        $plan = $this->plan($u, $p, ['asset_id' => $a->id, 'checklist' => [['text' => 'Inspect HVAC filters', 'done' => false]]]);
        $job = new GenerateMaintenance;
        $job->handle(app(Workflow::class));
        $job->handle(app(Workflow::class));
        $this->assertDatabaseCount('work_orders', 1);
        $w = WorkOrder::sole();
        $this->assertSame($plan->id, $w->plan_id);
        $this->assertSame($a->id, $w->asset_id);
        $this->assertSame(now()->addMonthsNoOverflow(3)->toDateString(), $plan->fresh()->next_on->toDateString());
        Queue::fake();
        $this->artisan('gnosix:maintenance-generate')->assertSuccessful();
        Queue::assertPushed(GenerateMaintenance::class);
        $this->get(route('app.maintenance.plan.show', [$o, $plan->uuid]))->assertOk();
    }

    public function test_all_recurrences_preserve_month_end_and_usage_does_not_fabricate_data(): void
    {
        [$u,$o,$p] = $this->fixture();
        $this->travelTo(Carbon::parse('2026-01-31 12:00:00'));
        foreach (['daily', 'weekly', 'monthly', 'quarterly', 'annually', 'custom', 'usage'] as $frequency) {
            $plan = $this->plan($u, $p, ['frequency' => $frequency, 'anchor_on' => '2026-01-31']);
            $count = app(Workflow::class)->generate($plan);
            $this->assertSame($frequency === 'usage' ? 0 : 1, $count);
            if ($frequency === 'usage') {
                $this->assertNull($plan->next_on);
            }
        }
        $monthly = MaintenancePlan::where('frequency', 'monthly')->sole();
        $this->travelTo(Carbon::parse('2026-02-28 12:00:00'));
        app(Workflow::class)->generate($monthly);
        $this->assertSame('2026-03-31', $monthly->fresh()->next_on->toDateString());
        $this->travelBack();
    }

    public function test_plan_suspend_resume_and_creator_grant_revocation(): void
    {
        [$u,$o,$p] = $this->fixture();
        $plan = $this->plan($u, $p);
        app(Workflow::class)->suspend($u, $plan, ['revision' => 1, 'state' => 'suspended', 'reason' => 'Seasonal shutdown']);
        $this->assertSame(0, app(Workflow::class)->generate($plan));
        app(Workflow::class)->suspend($u, $plan->fresh(), ['revision' => 2, 'state' => 'active', 'reason' => 'Season restarted']);
        $this->assertSame(1, app(Workflow::class)->generate($plan));
        $o->capabilityOverrides()->create(['capability' => 'hospitality.maintenance', 'enabled' => false]);
        $this->get(route('app.maintenance.index', $o))->assertForbidden();
    }

    public function test_documentary_completion_and_candidate_keep_existing_asset_facts(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $w = $this->work($u, $p, ['asset_id' => $a->id]);
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'upload']), ['files' => [UploadedFile::fake()->image('report.png')]])->assertRedirect();
        $att = $w->attachments()->sole();
        $url = URL::temporarySignedRoute('app.maintenance.work.file', now()->addMinutes(10), [$o, 'uuid' => $w->uuid, 'attachment' => $att->id, 'viewer' => $u->id], false);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($url.'x')->assertForbidden();
        $this->perform($u, $w);
        $this->transition($u, $w, 'resolved');
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'candidate']), ['semantic_key' => 'equipment.serial_number', 'value' => 'NEW-123', 'version_id' => $att->version_id, 'quote' => 'New serial NEW-123', 'page' => 1])->assertRedirect();
        $this->assertDatabaseHas('evidence_assertions', ['status' => 'proposed', 'original_value' => 'NEW-123']);
        $this->assertNull($a->fresh()->manual_data['serial_number']);
        $this->assertDatabaseCount('asset_fact_applications', 0);
        $this->transition($u, $w, 'verified');
        $this->transition($u, $w, 'closed');
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertOk()->assertSee('report.png');
    }

    public function test_multiple_interventions_block_premature_issue_closure(): void
    {
        [$u,$o,$p] = $this->fixture();
        $i = $this->issue($u, $p);
        $w = $this->work($u, $p, ['issue_ids' => [$i->id]]);
        $w2 = $this->work($u, $p, ['issue_ids' => [$i->id]]);
        $this->assertCount(2, $i->workOrders);
        $this->post(route('app.maintenance.issue.action', [$o, $i->uuid, 'transition']), ['revision' => 1, 'state' => 'closed', 'reason' => 'Premature'])->assertSessionHasErrors('state');
    }

    public function test_unknown_costs_and_warranty_render_without_fabrication_in_both_locales(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $w = $this->work($u, $p, ['asset_id' => $a->id]);
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertOk()->assertSee('Unknown / not recorded');
        $this->assertNull($w->labor_cost);
        $this->assertNull($a->manual_data['warranty_end']);
        $this->get(route('app.maintenance.index', [$o, 'lang' => 'ro', 'view' => 'board']))->assertOk()->assertSee('Centrul de mentenanță');
    }

    public function test_list_search_filters_pagination_and_portfolio_scope(): void
    {
        [$u,$o,$p] = $this->fixture();
        $p2 = $o->properties()->create(['name' => 'Resort second property', 'property_type' => 'resort', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        for ($n = 0; $n < 26; $n++) {
            $this->work($u, $n % 2 ? $p : $p2, ['title' => 'Order '.$n]);
        }$response = $this->get(route('app.maintenance.index', $o))->assertOk();
        $this->assertCount(24, $response->viewData('records')->items());
        $filtered = $this->get(route('app.maintenance.index', [$o, 'property' => $p2->id, 'q' => 'Order 25']))->assertOk();
        $this->assertSame(0, $filtered->viewData('records')->total());
        $this->get(route('app.maintenance.index', [$o, 'view' => 'board']))->assertOk()->assertSee('This board shows the current page');
    }

    public function test_forms_and_building_asset_entry_points_render(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        foreach (['issue', 'work', 'plan'] as $kind) {
            $this->get(route('app.maintenance.'.$kind.'.create', $o))->assertOk();
            $this->get(route('app.maintenance.'.$kind.'.create', [$o, 'property' => $p->id]))->assertOk();
        }
        $this->get(route('app.assets.show', [$o, $a->uuid]))->assertOk()->assertSee('Report an issue');
        $this->get(route('app.declarations.show', [$o, $p, $b->uuid]))->assertOk()->assertSee('Report an issue');
    }

    public function test_reporter_uploads_after_submission_but_cannot_read_unrelated_documents_or_drafts(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $reporter = $this->staff($o, 'reporter');
        $issue = $this->issue($reporter, $p, ['asset_id' => $a->id]);
        $draft = $this->issue($u, $p, ['title' => 'Private draft', 'submit' => 0]);
        $this->login($reporter);
        $this->post(route('app.maintenance.issue.action', [$o, $issue->uuid, 'upload']), ['files' => [UploadedFile::fake()->image('symptom.png')]])->assertRedirect();
        $this->assertCount(1, $issue->attachments);
        $this->get(route('app.maintenance.issue.show', [$o, $draft->uuid]))->assertForbidden();
        $this->get(route('app.maintenance.index', [$o, 'tab' => 'issues']))->assertDontSee('Private draft');
        $this->get(route('app.evidence.show', [$o, $p, $b->uuid]))->assertForbidden();
        $tech = $this->staff($o);
        $w = $this->work($u, $p, ['assigned_to' => $reporter->id]);
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'perform']), ['revision' => 1, 'work_performed' => 'Unauthorized'])->assertForbidden();
    }

    public function test_checklist_cannot_be_removed_by_technician_and_parts_keep_identity_and_history(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $tech = $this->staff($o);
        $w = $this->work($u, $p, ['assigned_to' => $tech->id, 'checklist' => [['text' => 'Safety test', 'done' => false]]]);
        $this->login($tech);
        $this->post(route('app.maintenance.work.action', [$o, $w->uuid, 'perform']), ['revision' => 1, 'work_performed' => 'Bypass', 'checklist' => []])->assertSessionHasErrors('checklist');
        $this->perform($tech, $w, ['checklist' => [['text' => 'Safety test', 'done' => true]], 'parts' => [['name' => 'Replacement component', 'asset_reference' => $a->reference, 'quantity' => 1]]]);
        $this->assertSame($a->id, $w->fresh()->parts[0]['asset_id']);
        $this->assertNull($w->fresh()->materials_cost);
        $event = $w->history()->first();
        $this->assertSame('Replacement component', $event->after_data['parts'][0]['name']);
        $this->assertTrue($event->after_data['checklist'][0]['done']);
        $this->perform($tech, $w, ['checklist' => [['text' => 'Safety test', 'done' => true]], 'parts' => []]);
        $this->assertSame([], $w->fresh()->parts);
        $this->assertSame('Replacement component', $event->fresh()->after_data['parts'][0]['name']);
    }

    public function test_portfolio_filters_cannot_expand_property_grants(): void
    {
        [$u,$o,$p] = $this->fixture();
        $g = $o->groups()->create(['name' => 'Mountain brand', 'kind' => 'brand']);
        $g->properties()->attach($p);
        $w = $this->work($u, $p);
        $other = $o->groups()->create(['name' => 'Other brand', 'kind' => 'brand']);
        $resp = $this->get(route('app.maintenance.index', [$o, 'group' => $g->id]))->assertOk();
        $this->assertSame(1, $resp->viewData('records')->total());
        $resp = $this->get(route('app.maintenance.index', [$o, 'group' => $other->id]))->assertOk();
        $this->assertSame(0, $resp->viewData('records')->total());
    }

    public function test_building_retention_suspends_plans_and_preserves_operational_identity(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $w = $this->work($u, $p, ['asset_id' => $a->id]);
        $plan = $this->plan($u, $p, ['asset_id' => $a->id]);
        $uuid = $w->uuid;
        $this->post(route('app.maintenance.work.action', [$o, $uuid, 'upload']), ['files' => [UploadedFile::fake()->image('report.png')]])->assertRedirect();
        DB::transaction(function () use ($b, $u) {
            app(Registry::class)->purgeBuilding($b, $u);
            app(Retention::class)->purge($b);
        });
        $this->assertSame('suspended', $plan->fresh()->state);
        $this->assertSame('blocked', $w->fresh()->state);
        $this->assertNull($w->fresh()->building_id);
        $this->assertSame($uuid, $w->fresh()->uuid);
        $this->assertNull($w->attachments()->sole()->version_id);
        $this->assertSame(0, app(Workflow::class)->generate($plan->fresh()));
    }

    public function test_conflicts_remain_visible_without_replacing_confirmed_asset_facts(): void
    {
        [$u,$o,$p,$b,$s,$a] = $this->fixture();
        $engine = app(Engine::class);
        $one = $engine->propose($u, $b, ['semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => $a->reference, 'value' => '00123']);
        $engine->review($u, $b, $one->id, 1, 'confirm', 'Reviewed');
        $two = $engine->propose($u, $b, ['semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => $a->reference, 'value' => '123']);
        $w = $this->work($u, $p, ['asset_id' => $a->id]);
        $r = $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertOk();
        $this->assertSame(1, $r->viewData('conflicts'));
        $this->assertNull($a->fresh()->manual_data['serial_number']);
        $this->assertSame('confirmed', $one->fresh()->status);
    }

    public function test_scheduler_notifications_are_opt_in_and_retry_does_not_duplicate_work(): void
    {
        [$u,$o,$p] = $this->fixture();
        $tech = $this->staff($o);
        Queue::fake();
        $plan = $this->plan($u, $p, ['assigned_to' => $tech->id, 'notify_assignee' => true]);
        app(Workflow::class)->generate($plan);
        app(Workflow::class)->generate($plan->fresh());
        Queue::assertPushed(NotifyMaintenance::class, 1);
        $this->assertDatabaseCount('work_orders', 1);
        Notification::fake();
        (new NotifyMaintenance(WorkOrder::sole()->id))->handle();
        Notification::assertSentTo($tech, MaintenanceAssigned::class);
    }

    public function test_platform_settings_require_separate_admin_and_recent_authentication(): void
    {
        [$u,$o,$p] = $this->fixture();
        $this->put(route('admin.maintenance.update'), ['repeat_days' => 120, 'repeat_threshold' => 4])->assertRedirect();
        $this->assertDatabaseMissing('platform_settings', ['key' => 'maintenance']);
        $admin = PlatformAdmin::create(['name' => 'Admin', 'email' => 'maintenance.admin@example.test', 'password' => 'Admin-Maintenance-2026!']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_confirmed_at' => time()]);
        $this->put(route('admin.maintenance.update'), ['repeat_days' => 120, 'repeat_threshold' => 4])->assertRedirect();
        $this->assertSame(['repeat_days' => 120, 'repeat_threshold' => 4], app(Settings::class)->all());
        $this->get(route('admin.settings', ['tab' => 'maintenance']))->assertOk()->assertSee('Fereastră de observație');
        $this->put(route('admin.maintenance.update'), ['repeat_days' => 0, 'repeat_threshold' => 1])->assertSessionHasErrors();
        $this->assertSame(120, app(Settings::class)->all()['repeat_days']);
        $this->withSession(['platform_admin_confirmed_at' => 0])->putJson(route('admin.maintenance.update'), ['repeat_days' => 90, 'repeat_threshold' => 3])->assertStatus(423);
    }

    public function test_new_manager_can_take_over_schedule_without_rewriting_creator(): void
    {
        [$u,$o,$p] = $this->fixture();
        $plan = $this->plan($u, $p);
        $manager = $this->staff($o, 'manager');
        $membership = $o->memberships()->where('user_id', $u->id)->first();
        $membership->update(['role' => 'reporter']);
        $this->assertSame(0, app(Workflow::class)->generate($plan));
        // An existing plan can be saved by a new authorized coordinator, preserving its creator.
        $original = MaintenancePlan::where('created_by', $u->id)->sole();
        $d = $this->data(['frequency' => 'quarterly', 'anchor_on' => now()->toDateString(), 'interval_count' => 1, 'interval_unit' => 'month', 'notify_assignee' => false, 'revision' => $original->revision]);
        app(Workflow::class)->plan($manager, $p, $d, $original);
        $this->assertSame($u->id, $original->fresh()->created_by);
        $this->assertSame($manager->id, $original->fresh()->managed_by);
        $this->assertSame(1, app(Workflow::class)->generate($original->fresh()));
    }
}
