<?php

namespace Tests\Feature;

use App\Assets\Registry;
use App\Evidence\Engine;
use App\Field\Invitations;
use App\Field\QrRegistry;
use App\Field\Settings;
use App\Maintenance\Documents;
use App\Maintenance\Workflow;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetType;
use App\Models\Evidence\Version;
use App\Models\FieldInvitation;
use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use App\Models\ServiceReport;
use App\Models\User;
use App\Support\BuildingDeclarationStudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class FieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('evidence');
        Http::preventStrayRequests();
        $u = User::factory()->create(['locale' => 'en', 'onboarding_completed_at' => now(), 'profile_type' => 'manager']);
        $o = Organization::create(['name' => 'Atlas field operator']);
        $o->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $o->memberships()->create(['user_id' => $u->id, 'role' => 'owner']);
        $p = $o->properties()->create(['name' => 'Field hotel', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $b = $p->buildings()->create(['name' => 'Private building', 'state' => 'active']);
        $f = $b->floors()->create(['name' => 'Level 1', 'position' => 1]);
        $s = $p->spaces()->create(['name' => 'Room 101', 'building_id' => $b->id, 'floor_id' => $f->id, 'space_type' => 'room']);
        $a = app(Registry::class)->save($u, $p, ['name' => 'CT-01 HVAC', 'asset_type_id' => AssetType::where('key', 'hvac')->value('id'), 'space_id' => $s->id, 'status' => 'unknown', 'serial_number' => '00123']);
        $w = app(Workflow::class)->work($u, $p, ['title' => 'Cooling issue', 'description' => 'Warm air reported', 'category' => 'cooling', 'priority' => 'normal', 'asset_id' => $a->id, 'checklist' => [['text' => 'Inspect filter', 'done' => false]]]);
        $this->login($u);

        return [$u, $o, $p, $b, $f, $s, $a, $w];
    }

    private function login(User $u): void
    {
        $this->actingAs($u, 'web')->withSession(['customer_auth_version' => $u->auth_version, 'customer_locale' => 'en']);
    }

    private function external(): User
    {
        return User::factory()->create(['locale' => 'en']);
    }

    private function invite($u, $p, $w, $who = null, $extra = []): array
    {
        return app(Invitations::class)->create($u, $p, ['work_ids' => [$w->id], 'email' => $who?->email, 'expires_at' => now($p->timezone)->addDay()->format('Y-m-d\TH:i'), 'allow_costs' => false, ...$extra]);
    }

    private function grant($u, $p, $w, $who, $extra = []): FieldInvitation
    {
        [$g,$token] = $this->invite($u, $p, $w, $who, $extra);
        app(Invitations::class)->redeem($who, $token);

        return $g->fresh();
    }

    private function data($w, $extra = []): array
    {
        return ['revision' => $w->fresh()->revision, 'submit' => 1, 'ack' => 1, 'work_performed' => 'Filter cleaned and airflow measured.', 'observed_condition' => 'Cooling restored.', 'minutes_spent' => 35, 'checklist' => [['text' => 'Inspect filter', 'done' => true]], ...$extra];
    }

    public function test_qr_png_svg_opaque_payload_and_guest_privacy(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $q = app(QrRegistry::class)->generate($u, $p, 'asset', $a->uuid);
        $l = $q->active();
        $this->assertSame(48, strlen($l->locator));
        $this->assertStringContainsString('/q/'.$l->locator, app(QrRegistry::class)->url($l));
        $this->assertStringNotContainsString($a->uuid, app(QrRegistry::class)->url($l));
        $this->assertStringStartsWith("\x89PNG", app(QrRegistry::class)->render($l, 'png'));
        $this->assertStringContainsString('<svg', app(QrRegistry::class)->render($l));
        $this->get(route('app.qr.image', [$o, $q->uuid, 'png']))->assertOk()->assertHeader('Content-Type', 'image/png');
        Auth::guard('web')->logout();
        $this->get(route('field.qr.resolve', $l->locator))->assertOk()->assertDontSee('CT-01 HVAC')->assertDontSee('Private building')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('field.qr.resolve', str_repeat('a', 48)))->assertOk()->assertDontSee('Private building');
    }

    public function test_qr_rotation_revocation_and_all_supported_targets_preserve_uuid(): void
    {
        [$u,$o,$p,$b,$f,$s,$a] = $this->fixture();
        foreach (['asset' => $a, 'building' => $b, 'floor' => $f, 'space' => $s] as $type => $target) {
            $q = app(QrRegistry::class)->generate($u, $p, $type, $target->uuid);
            $old = $q->active();
            $again = app(QrRegistry::class)->generate($u, $p, $type, $target->uuid, 'rotate');
            $this->assertSame($q->uuid, $again->uuid);
            $this->assertNotSame($old->locator, $again->active()->locator);
            $this->assertNotNull($old->fresh()->revoked_at);
            app(QrRegistry::class)->generate($u, $p, $type, $target->uuid, 'revoke');
            $this->assertNull($q->active());
            $this->get(route('field.qr.resolve', $old->locator))->assertOk()->assertDontSee($target->name);
        }
    }

    public function test_qr_archival_restore_and_scoped_external_scan(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $this->grant($u, $p, $w, $tech);
        $qr = app(QrRegistry::class)->generate($u, $p, 'asset', $a->uuid);
        $locator = $qr->active()->locator;
        $this->login($tech);
        $this->get(route('field.qr.resolve', $locator))->assertOk()->assertSee('CT-01 HVAC');
        $b->delete();
        $this->get(route('field.qr.resolve', $locator))->assertOk()->assertDontSee('CT-01 HVAC');
        $b->restore();
        $this->get(route('field.qr.resolve', $locator))->assertSee('CT-01 HVAC');
        $this->assertSame($locator, $qr->active()->locator);
    }

    public function test_single_use_invitation_is_hashed_email_bound_and_idempotent_for_same_account(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        [$g,$token] = $this->invite($u, $p, $w, $tech);
        $this->assertSame(hash('sha256', $token), $g->token_hash);
        $this->assertStringNotContainsString($token, json_encode($g));
        $this->login($tech);
        $this->post(route('field.redeem'), ['invitation_token' => $token])->assertRedirect();
        $this->post(route('field.redeem'), ['invitation_token' => $token])->assertRedirect();
        $this->assertSame(0, $tech->memberships()->count());
        $this->assertDatabaseCount('field_invitations', 1);
        $this->assertSame(1, AssetAudit::where('action', 'field.redeemed')->count());
        $this->login($this->external());
        $this->post(route('field.redeem'), ['invitation_token' => $token])->assertForbidden();
        $this->assertNull(session()->getOldInput('invitation_token'));
    }

    public function test_expiry_revocation_withdrawal_and_cancelled_order_deny_existing_access(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $g = $this->grant($u, $p, $w, $tech);
        $this->login($tech);
        $this->get(route('field.work', $w->uuid))->assertOk();
        app(Invitations::class)->revoke($u, $g);
        $this->get(route('field.work', $w->uuid))->assertForbidden();
        $g2 = $this->grant($u, $p, $w, $tech);
        $g2->update(['expires_at' => now()->subSecond()]);
        $this->get(route('field.work', $w->uuid))->assertForbidden();
        $g3 = $this->grant($u, $p, $w, $tech);
        app(Workflow::class)->transition($u, $w, ['revision' => 1, 'state' => 'cancelled', 'reason' => 'Cancelled']);
        $this->get(route('field.work', $w->uuid))->assertForbidden();
    }

    public function test_inviter_losing_rights_revokes_scoped_grant_without_membership(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $this->grant($u, $p, $w, $tech);
        $o->memberships()->where('user_id', $u->id)->update(['role' => 'reporter']);
        $this->login($tech);
        $this->get(route('field.work', $w->uuid))->assertForbidden();
    }

    public function test_other_work_order_and_general_evidence_are_not_accessible(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $other = app(Workflow::class)->work($u, $p, ['title' => 'Confidential unrelated work', 'category' => 'other', 'priority' => 'normal']);
        $tech = $this->external();
        $this->grant($u, $p, $w, $tech);
        $this->login($tech);
        $this->get(route('field.work', $other->uuid))->assertForbidden();
        $this->get(route('app.evidence.show', [$o, $p, $b->uuid]))->assertForbidden();
        $this->get(route('app.assets.show', [$o, $a->uuid]))->assertForbidden();
        $this->get(route('field.index'))->assertOk()->assertDontSee('Confidential unrelated work');
    }

    public function test_explicit_document_share_and_withdrawal_are_signed_and_scoped(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $g = $this->grant($u, $p, $w, $tech);
        $v = app(Engine::class)->uploads($u, $b, [UploadedFile::fake()->image('private-manual.png')], 'manual')[0]['version'];
        $url = URL::temporarySignedRoute('field.document', now()->addMinutes(10), ['uuid' => $w->uuid, 'version' => $v->id, 'viewer' => $tech->id], false);
        $this->login($tech);
        $this->get($url)->assertNotFound();
        $g->documents()->create(['version_id' => $v->id, 'source_uuid' => $v->id, 'created_at' => now()]);
        $this->get($url)->assertOk();
        $this->get($url.'x')->assertForbidden();
        $g->documents()->delete();
        $this->get($url)->assertNotFound();
        $g->documents()->create(['version_id' => $v->id, 'source_uuid' => $v->id, 'created_at' => now()]);
        app(Invitations::class)->revoke($u, $g);
        $this->get($url)->assertForbidden();
    }

    public function test_external_upload_capture_and_cost_permission_validation(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $g = $this->grant($u, $p, $w, $tech);
        $this->login($tech);
        $this->post(route('field.work.action', [$w->uuid, 'upload']), ['files' => [UploadedFile::fake()->image('completion.jpg')]])->assertRedirect();
        $this->assertSame(1, $g->documents()->count());
        $this->post(route('field.work.action', [$w->uuid, 'report']), $this->data($w, ['labor_cost' => 50, 'currency' => 'RON']))->assertForbidden();
        $this->assertDatabaseCount('service_reports', 0);
    }

    public function test_mobile_report_revision_return_review_accept_and_private_pdf(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $this->grant($u, $p, $w, $tech);
        $this->login($tech);
        $this->post(route('field.work.action', [$w->uuid, 'report']), $this->data($w))->assertRedirect();
        $r = ServiceReport::sole();
        $this->assertSame('submitted', $r->status());
        $this->assertSame('resolved', $w->fresh()->state);
        $this->assertSame(35, $w->fresh()->minutes_spent);
        $this->assertNotNull($r->version_id);
        $pdf = Storage::disk('evidence')->get($r->version->path);
        $this->assertStringStartsWith('%PDF-', $pdf);
        if (is_dir(base_path('build'))) {
            file_put_contents(base_path('build/service-report-test.pdf'), $pdf);
        }
        $this->login($u);
        $this->post(route('field.report.review', [$w->uuid, $r->uuid]), ['sequence' => 0, 'decision' => 'returned', 'reason' => 'Please add the test result.'])->assertRedirect();
        $this->assertSame('returned', $r->status());
        $this->assertSame('in_progress', $w->fresh()->state);
        $this->login($tech);
        $this->post(route('field.work.action', [$w->uuid, 'report']), $this->data($w, ['work_performed' => 'Filter cleaned. Temperature test completed.']))->assertRedirect();
        $r2 = $w->serviceReports()->latest('number')->first();
        $this->assertSame(2, $r2->number);
        $this->assertSame('Filter cleaned and airflow measured.', $r->fresh()->snapshot['work_performed']);
        $this->login($u);
        foreach (['reviewed', 'accepted'] as $i => $decision) {
            $this->post(route('field.report.review', [$w->uuid, $r2->uuid]), ['sequence' => $i, 'decision' => $decision, 'reason' => 'Sources and test results reviewed.'])->assertRedirect();
        }$this->assertSame('accepted', $r2->status());
        $this->assertSame('closed', $w->fresh()->state);
        $accepted = $r2->reviews()->where('decision', 'accepted')->sole();
        $acceptedVersion = Version::findOrFail($accepted->version_id);
        $this->assertNotSame($r2->version_id, $acceptedVersion->id);
        $this->assertSame('en', $r2->snapshot['locale']);
        if (is_dir(base_path('build'))) {
            file_put_contents(base_path('build/service-report-accepted-test.pdf'), Storage::disk('evidence')->get($acceptedVersion->path));
        }
        $this->assertSame($u->id, $w->fresh()->verified_by);
        $this->assertDatabaseHas('asset_document_links', ['asset_id' => $a->id]);
        $this->assertGreaterThan(0, AssetAudit::where('asset_id', $a->id)->where('action', 'maintenance.report_accepted')->count());
        $this->login($tech);
        $url = URL::temporarySignedRoute('field.report.pdf', now()->addMinutes(10), ['uuid' => $w->uuid, 'report' => $r2->uuid, 'viewer' => $tech->id], false);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_draft_and_required_photo_do_not_fabricate_completion(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $w->update(['require_photo' => true, 'assigned_to' => $u->id]);
        $this->post(route('field.work.action', [$w->uuid, 'report']), $this->data($w, ['submit' => 0, 'ack' => null]))->assertRedirect();
        $draft = ServiceReport::sole();
        $this->assertSame('draft', $draft->state);
        $this->assertNull($draft->version_id);
        $this->post(route('field.work.action', [$w->uuid, 'report']), $this->data($w))->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('service_reports', 1);
    }

    public function test_submitted_serial_creates_reviewable_conflict_without_changing_confirmed_facts(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $engine = app(Engine::class);
        $source = $engine->propose($u, $b, ['semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => $a->reference, 'value' => '00123']);
        $engine->review($u, $b, $source->id, 1, 'confirm', 'Verified source');
        $tech = $this->external();
        $this->grant($u, $p, $w, $tech);
        $this->login($tech);
        $this->post(route('field.work.action', [$w->uuid, 'report']), $this->data($w, ['observed_serial' => '123']))->assertRedirect();
        $this->assertSame('00123', $a->fresh()->manual_data['serial_number']);
        $this->assertSame('confirmed', $source->fresh()->status);
        $this->assertDatabaseHas('evidence_assertions', ['original_value' => '123', 'status' => 'proposed']);
        $this->assertDatabaseCount('evidence_conflicts', 1);
        $this->get(route('field.work', $w->uuid))->assertOk()->assertSee('no confirmed fact was overwritten');
    }

    public function test_stale_concurrent_report_and_review_reject_without_duplicate_documents(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $data = $this->data($w);
        $this->post(route('field.work.action', [$w->uuid, 'report']), $data)->assertRedirect();
        $r = ServiceReport::sole();
        $this->post(route('field.work.action', [$w->uuid, 'report']), $data)->assertSessionHasErrors('revision');
        $this->assertDatabaseCount('service_reports', 1);
        $this->post(route('field.report.review', [$w->uuid, $r->uuid]), ['sequence' => 0, 'decision' => 'reviewed', 'reason' => 'Reviewed'])->assertRedirect();
        $this->post(route('field.report.review', [$w->uuid, $r->uuid]), ['sequence' => 0, 'decision' => 'accepted', 'reason' => 'Stale'])->assertSessionHasErrors('report');
        $this->assertSame('reviewed', $r->status());
    }

    public function test_cross_tenant_labels_and_invitation_scopes_are_denied(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $other = Organization::create(['name' => 'Other tenant']);
        $other->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $other->memberships()->create(['user_id' => $u->id, 'role' => 'owner']);
        $p2 = $other->properties()->create(['name' => 'Other property', 'property_type' => 'house', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $this->post(route('app.qr.mutate', $o), ['property_id' => $p->id, 'type' => 'asset', 'target' => (string) Str::uuid(), 'action' => 'generate'])->assertNotFound();
        $this->post(route('app.field.invite', $other), ['property_id' => $p2->id, 'work_ids' => [$w->id], 'expires_at' => now()->addDay()->format('Y-m-d\TH:i'), 'allow_costs' => 0])->assertSessionHasErrors('work_ids');
    }

    public function test_bulk_labels_are_bounded_and_use_pagination_with_large_registry(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $rows = [];
        for ($i = 0; $i < 10000; $i++) {
            $rows[] = ['uuid' => (string) Str::uuid(), 'reference' => 'AST-BULK-'.$i, 'organization_id' => $o->id, 'property_id' => $p->id, 'asset_type_id' => $a->asset_type_id, 'name' => 'Bulk asset '.$i, 'status' => 'unknown', 'manual_data' => '{}', 'revision' => 1, 'is_system' => false, 'created_at' => now(), 'updated_at' => now()];
        }foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('assets')->insert($chunk);
        }$response = $this->get(route('app.qr.index', [$o, 'property' => $p->id]))->assertOk();
        $this->assertCount(24, $response->viewData('targets')->items());
        $targets = Asset::where('property_id', $p->id)->limit(3)->pluck('uuid')->all();
        $this->post(route('app.qr.labels', $o), ['property_id' => $p->id, 'type' => 'asset', 'targets' => $targets])->assertOk()->assertSee('Print labels');
        $this->post(route('app.qr.labels', $o), ['property_id' => $p->id, 'type' => 'asset', 'targets' => array_column(array_slice($rows, 0, 101), 'uuid')])->assertSessionHasErrors('targets');
        $this->assertDatabaseCount('qr_identities', 3);
    }

    public function test_manager_pages_and_member_technician_use_existing_identity(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $this->get(route('app.field.manage', [$o, 'property' => $p->id]))->assertOk();
        $this->get(route('app.qr.index', $o))->assertOk();
        $tech = $this->external();
        $o->memberships()->create(['user_id' => $tech->id, 'role' => 'technician']);
        $w->update(['assigned_to' => $tech->id]);
        $this->login($tech);
        $this->get(route('field.index'))->assertOk()->assertSee('Cooling issue');
        $this->get(route('field.work', $w->uuid))->assertOk()->assertDontSee('Confidential');
        $this->post(route('field.report.review', [$w->uuid, Str::uuid()]), ['sequence' => 0, 'decision' => 'accepted', 'reason' => 'No'])->assertNotFound();
    }

    public function test_asset_scope_covers_only_matching_asset_and_purge_revokes_the_grant(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $g = $this->grant($u, $p, $w, $tech, ['work_ids' => [], 'asset_id' => $a->id]);
        $second = app(Workflow::class)->work($u, $p, ['title' => 'Second same asset', 'category' => 'cooling', 'priority' => 'normal', 'asset_id' => $a->id]);
        $other = app(Workflow::class)->work($u, $p, ['title' => 'Different asset scope', 'category' => 'other', 'priority' => 'normal', 'building_id' => $b->id]);
        $this->login($tech);
        $this->get(route('field.work', $second->uuid))->assertOk();
        $this->get(route('field.work', $other->uuid))->assertForbidden();
        $b->delete();
        app(BuildingDeclarationStudio::class)->purge($u, $p, $b, $b->fresh()->declaration_version);
        $this->assertNotNull($g->fresh()->revoked_at);
        $this->get(route('field.work', $second->uuid))->assertForbidden();
    }

    public function test_verified_identity_preview_and_invitation_duration_are_enforced(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        [$g,$token] = $this->invite($u, $p, $w, $tech);
        $this->login($tech);
        $this->post(route('field.preview'), ['invitation_token' => $token])->assertOk()->assertSee($o->name);
        $this->assertNull($g->fresh()->redeemed_by);
        $tech->forceFill(['email_verified_at' => null])->save();
        $this->post(route('field.redeem'), ['invitation_token' => $token])->assertRedirect(route('verification.notice'));
        $this->assertNull($g->fresh()->redeemed_by);
        $this->login($u);
        PlatformSetting::updateOrCreate(['key' => 'field'], ['value' => ['invite_max_days' => 1]]);
        $this->post(route('app.field.invite', $o), ['property_id' => $p->id, 'work_ids' => [$w->id], 'allow_costs' => false, 'expires_at' => now()->addDays(2)->format('Y-m-d\TH:i')])->assertSessionHasErrors('expires_at');
    }

    public function test_field_settings_require_separate_recently_authenticated_platform_admin(): void
    {
        [$u] = $this->fixture();
        $this->put(route('admin.field.update'), ['invite_max_days' => 30])->assertRedirect();
        $this->assertDatabaseMissing('platform_settings', ['key' => 'field']);
        $admin = PlatformAdmin::create(['name' => 'Admin', 'email' => 'field.admin@example.test', 'password' => 'Admin-Field-2026!']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_confirmed_at' => time()]);
        $this->put(route('admin.field.update'), ['invite_max_days' => 30])->assertRedirect();
        $this->assertSame(30, app(Settings::class)->all()['invite_max_days']);
        $this->get(route('admin.settings', ['tab' => 'field']))->assertOk()->assertSee('Durata maximă');
        $this->put(route('admin.field.update'), ['invite_max_days' => 366])->assertSessionHasErrors('invite_max_days');
        $this->withSession(['platform_admin_confirmed_at' => 0])->putJson(route('admin.field.update'), ['invite_max_days' => 10])->assertStatus(423);
    }

    public function test_member_without_property_access_cannot_expand_a_field_invitation_via_legacy_routes(): void
    {
        [$u,$o,$p,$b,$f,$s,$a,$w] = $this->fixture();
        $tech = $this->external();
        $o->memberships()->create(['user_id' => $tech->id, 'role' => 'technician', 'access_scope' => 'properties']);
        app(Documents::class)->upload($u, $w, [UploadedFile::fake()->image('unshared-internal.png')]);
        $attachment = $w->attachments()->sole();
        $this->grant($u, $p, $w, $tech);
        $this->login($tech);
        $this->get(route('field.work', $w->uuid))->assertOk()->assertDontSee('unshared-internal.png');
        $this->get(route('app.maintenance.work.show', [$o, $w->uuid]))->assertForbidden();
        $url = URL::temporarySignedRoute('app.maintenance.work.file', now()->addMinutes(10), ['organization' => $o, 'uuid' => $w->uuid, 'attachment' => $attachment->id, 'viewer' => $tech->id], false);
        $this->get($url)->assertForbidden();
    }
}
