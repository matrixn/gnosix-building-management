<?php

namespace Tests\Feature;

use App\Evidence\Engine;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\MaintenanceIssue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use App\Trust\Passport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class TrustPassportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Property $property;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidence');
        $this->owner = User::factory()->create(['profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $this->organization = Organization::create(['name' => 'Source hotel chain']);
        $this->organization->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $this->organization->memberships()->create(['user_id' => $this->owner->id, 'role' => 'owner']);
        $this->property = $this->organization->properties()->create(['name' => 'Main property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $this->building = $this->property->buildings()->create(['name' => 'Landmark Hotel', 'state' => 'active', 'address' => 'Private address']);
        $this->login($this->owner);
    }

    private function login(User $user): void
    {
        $this->actingAs($user, 'web')->withSession(['customer_auth_version' => $user->auth_version, 'customer_locale' => 'en']);
    }

    private function url(string $route, string ...$params): string
    {
        return route('app.trust.'.$route, [$this->organization, $this->property, $this->building->uuid, ...$params]);
    }

    private function upload(Building $building, string $name, int $size = 30)
    {
        return app(Engine::class)->uploads($this->owner, $building, [UploadedFile::fake()->image($name, $size, 25)], 'technical')[0]['version'];
    }

    private function revision(string $flow): int
    {
        return DB::table('building_handovers')->where('id', $flow)->value('revision');
    }

    private function action(string $flow, string $action, array $data = [])
    {
        return $this->post($this->url('action', $flow, $action), $data + ['revision' => $this->revision($flow)]);
    }

    private function prepared(array $data = []): string
    {
        $this->post($this->url('draft'))->assertRedirect();
        $flow = DB::table('building_handovers')->where('state', 'draft')->value('id');
        $this->get($this->url('flow', $flow))->assertOk()->assertSee('Handover Studio');
        $this->action($flow, 'prepare', $data + [
            'checks' => Passport::CHECKS, 'authority' => '1', 'authority_basis' => 'Contract reference GNX-6', 'access_days' => 0,
        ])->assertRedirect($this->url('flow', $flow))->assertSessionHasNoErrors();

        return $flow;
    }

    private function invite(string $flow, User $user, int $days = 7): string
    {
        $response = $this->action($flow, 'invite', ['email' => $user->email, 'days' => $days])
            ->assertOk()->assertSee('Invitation ready');
        $link = $response->viewData('link');
        $token = parse_url($link, PHP_URL_FRAGMENT);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertDatabaseMissing('handover_recipients', ['token_hash' => $token]);
        $this->assertDatabaseHas('handover_recipients', ['token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public function test_live_passport_explains_missing_and_disputed_information_in_its_own_building(): void
    {
        $elsewhere = $this->organization->properties()->create(['name' => 'Private second property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $otherBuilding = $elsewhere->buildings()->create(['name' => 'Other hotel', 'state' => 'active']);
        $this->upload($otherBuilding, 'Other hotel plans.png');
        $unavailable = $this->upload($this->building, 'Lost drawing.png', 40);
        Storage::disk('evidence')->delete($unavailable->path);
        $unavailable->document->update(['document_type' => 'installation']);

        $asset = Asset::create([
            'name' => 'Heating loop', 'organization_id' => $this->organization->id, 'property_id' => $this->property->id,
            'building_id' => $this->building->id, 'asset_type_id' => AssetType::where('key', 'hvac')->value('id'), 'is_system' => true,
            'manual_data' => ['warranty_end' => '2028-01-01'],
        ]);
        $work = WorkOrder::create([
            'organization_id' => $this->organization->id, 'property_id' => $this->property->id, 'building_id' => $this->building->id,
            'title' => 'Valve intervention', 'category' => 'leak', 'priority' => 'critical', 'state' => 'reported',
        ]);
        MaintenanceIssue::create([
            'organization_id' => $this->organization->id, 'property_id' => $this->property->id, 'building_id' => $this->building->id,
            'title' => 'Outlet issue', 'category' => 'leak', 'severity' => 'critical', 'state' => 'reported',
        ]);
        foreach (['SN-1', 'SN-2'] as $serial) {
            app(Engine::class)->propose($this->owner, $this->building, [
                'semantic_key' => 'equipment.serial_number', 'subject_type' => 'equipment', 'subject_reference' => 'Loop', 'value' => $serial,
            ]);
        }

        $page = $this->get($this->url('show'))->assertOk()->assertSee('Technical documentation missing')
            ->assertSee('Original unavailable')->assertSee('Declared warranty without')
            ->assertSee('Equipment serial number disputed')->assertSee('Technical system without a recorded relationship')
            ->assertSee('Outlet issue')->assertDontSee('Other hotel plans.png')->assertDontSee('Private address')
            ->assertSee(route('app.maintenance.work.show', [$this->organization, $work->uuid]))
            ->assertSee(route('app.assets.show', [$this->organization, $asset->uuid]));
        $passport = $page->viewData('passport');
        $this->assertSame([1, 5], $passport['coverage']['documentation']);
        $this->assertSame([0, 1], $passport['coverage']['identification']);
        $this->assertSame([1, 1], $passport['coverage']['warranty']);
        $this->assertSame([0, 2], $passport['coverage']['consistency']);
        $this->assertNull($passport['building']['construction_year']);
        $this->get(route('app.trust.index', $this->organization))->assertOk()->assertSee('Other hotel')->assertSee('Landmark Hotel');
        $this->get($this->url('show').'?lang=ro')->assertOk()->assertSee('Pregătirea predării');
    }

    public function test_external_recipient_can_review_only_offered_versions_then_receive_revocable_read_access(): void
    {
        $selected = $this->upload($this->building, 'Selected record.png');
        $private = $this->upload($this->building, 'Unselected plan.png', 40);
        $flow = $this->prepared(['sections' => ['evidence'], 'versions' => [$selected->id], 'access_days' => 14]);
        $recipient = User::factory()->create();
        $token = $this->invite($flow, $recipient);
        $this->get(route('handover.invitation'))->assertOk()->assertDontSee('Landmark Hotel')->assertDontSee($token);
        $this->post(route('handover.redeem'), ['invitation_token' => $token])->assertNotFound();
        $this->login($recipient);
        $this->post(route('handover.redeem'), ['invitation_token' => $token])->assertRedirect(route('handover.show', $flow));
        $this->post(route('handover.redeem'), ['invitation_token' => $token])->assertNotFound();
        $this->get(route('handover.show', $flow))->assertOk()->assertSee('Selected record.png')->assertDontSee('Unselected plan.png')
            ->assertDontSee('Contract reference GNX-6')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('handover.document', [$flow, $private->id]))->assertNotFound();
        $this->get(route('handover.document', [$flow, $selected->id]))->assertOk()->assertDownload('Selected record.png');
        $this->get($this->url('show'))->assertForbidden();

        $revision = $this->revision($flow);
        $this->post(route('handover.action', [$flow, 'question']), ['revision' => $revision, 'message' => 'Where is the electrical inspection?'])->assertRedirect();
        $this->login($this->owner);
        $question = DB::table('handover_events')->where(['handover_id' => $flow, 'action' => 'question'])->value('id');
        $this->get($this->url('flow', $flow))->assertOk()->assertSee('Where is the electrical inspection?')->assertDontSee($token);
        $this->action($flow, 'reply', ['message' => 'It is scheduled next week.', 'question' => $question])->assertRedirect();
        $this->login($recipient);
        $this->get(route('handover.show', $flow))->assertOk()->assertSee('It is scheduled next week.');
        $this->post(route('handover.action', [$flow, 'accept']), ['revision' => $revision, 'consent' => 1])->assertRedirect();
        $this->login($this->owner);
        $this->action($flow, 'complete', ['confirm' => 1])->assertRedirect();
        $this->action($flow, 'complete', ['confirm' => 1])->assertConflict();
        $this->assertDatabaseCount('building_stewardships', 1);
        $this->assertSame($this->building->uuid, $this->building->fresh()->uuid);
        $this->assertSame($this->property->id, $this->building->fresh()->property_id);
        $this->assertDatabaseMissing('organization_memberships', ['user_id' => $recipient->id]);
        $this->login($recipient);
        $this->get(route('handover.export', [$flow, 'json']))->assertOk()
            ->assertJsonPath('building.uuid', $this->building->uuid)
            ->assertJsonMissingPath('sections.assets')->assertJsonMissingPath('building.address');
        $this->building->delete();
        $this->get(route('handover.show', $flow))->assertNotFound();
        $this->get(route('handover.document', [$flow, $selected->id]))->assertNotFound();
        $this->building->restore();
        $this->get(route('handover.show', $flow))->assertOk();
        $this->login($this->owner);
        $this->action($flow, 'revoke', ['recipient' => DB::table('handover_recipients')->value('id'), 'message' => 'Access no longer required.'])->assertRedirect();
        $this->login($recipient);
        $this->get(route('handover.export', [$flow, 'json']))->assertNotFound();
        $this->get(route('handover.portal'))->assertOk()->assertDontSee('Landmark Hotel');
    }

    public function test_two_reviewers_must_agree_using_current_revision_and_return_closes_access(): void
    {
        $flow = $this->prepared();
        [$first, $second] = User::factory()->count(2)->create();
        $one = $this->invite($flow, $first);
        $two = $this->invite($flow, $second);
        $this->login($first);
        $this->post(route('handover.redeem'), ['invitation_token' => $one])->assertRedirect();
        $this->login($second);
        $this->post(route('handover.redeem'), ['invitation_token' => $two])->assertRedirect();
        $stale = $this->revision($flow);
        $this->login($first);
        $this->post(route('handover.action', [$flow, 'question']), ['revision' => $stale, 'message' => 'First reviewer private question'])->assertRedirect();
        $this->post(route('handover.action', [$flow, 'accept']), ['revision' => $stale, 'consent' => 1])->assertRedirect();
        $this->assertDatabaseHas('building_handovers', ['id' => $flow, 'state' => 'under_review']);
        $this->login($second);
        $this->get(route('handover.show', $flow))->assertOk()->assertDontSee('First reviewer private question');
        $this->post(route('handover.action', [$flow, 'accept']), ['revision' => $stale, 'consent' => 1])->assertConflict();
        $this->post(route('handover.action', [$flow, 'return']), ['revision' => $this->revision($flow), 'message' => 'Inventory correction required'])->assertRedirect(route('handover.portal'));
        $this->assertDatabaseHas('building_handovers', ['id' => $flow, 'state' => 'returned']);
        $this->get(route('handover.show', $flow))->assertNotFound();
        $this->login($first);
        $this->get(route('handover.show', $flow))->assertNotFound();
        $this->login($this->owner);
        $this->action($flow, 'complete', ['confirm' => 1])->assertConflict();
        $this->assertDatabaseCount('building_stewardships', 0);
    }

    public function test_expired_revoked_and_wrong_organization_invitations_disclose_nothing(): void
    {
        $target = Organization::create(['name' => 'Recipient organization']);
        $flow = $this->prepared(['recipient_org' => $target->id]);
        [$first, $second, $third] = User::factory()->count(3)->create();
        $firstToken = $this->invite($flow, $first, 1);
        $secondToken = $this->invite($flow, $second);
        $thirdToken = $this->invite($flow, $third);
        $target->memberships()->create(['user_id' => $first->id, 'role' => 'member']);
        $target->memberships()->create(['user_id' => $second->id, 'role' => 'member']);
        $this->action($flow, 'revoke', ['recipient' => DB::table('handover_recipients')->where('email', $second->email)->value('id'), 'message' => 'Invitation withdrawn.'])->assertRedirect();
        $this->travel(2)->days();
        foreach ([[$first, $firstToken], [$second, $secondToken], [$third, $thirdToken]] as [$user, $token]) {
            $this->login($user);
            $this->post(route('handover.redeem'), ['invitation_token' => $token])->assertNotFound();
            $this->get(route('handover.show', $flow))->assertNotFound();
        }
    }

    public function test_cross_property_versions_and_foreign_source_are_denied(): void
    {
        $foreignProperty = $this->organization->properties()->create(['name' => 'Another hotel', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $foreignBuilding = $foreignProperty->buildings()->create(['name' => 'Separate building', 'state' => 'active']);
        $foreign = $this->upload($foreignBuilding, 'Outside scope.png');
        $flow = $this->prepared();
        $this->action($flow, 'prepare', [
            'sections' => ['evidence'], 'versions' => [$foreign->id], 'checks' => Passport::CHECKS,
            'authority' => 1, 'authority_basis' => 'Contract reference', 'access_days' => 7,
        ])->assertUnprocessable();
        $this->get(route('app.trust.show', [$this->organization, $this->property, $foreignBuilding->uuid]))->assertNotFound();
        $this->login(User::factory()->create());
        $this->post($this->url('draft'))->assertForbidden();
        $this->get($this->url('export', 'json'))->assertForbidden();
    }

    public function test_exports_have_versioned_json_pdf_and_hashes_for_the_same_snapshot(): void
    {
        $this->upload($this->building, 'Inventory.png');
        $this->get($this->url('export', 'json'))->assertOk()->assertJsonPath('schema', 'gnosix.trust-passport.v1');
        $pdf = $this->get($this->url('export', 'pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $result = $this->get($this->url('export', 'zip'))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $path = $result->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('gnosix.trust-manifest.v1', $manifest['schema']);
            $this->assertSame(3, $zip->numFiles);
            foreach ($manifest['files'] as $name => $entry) {
                $bytes = $zip->getFromName($name);
                $this->assertSame(strlen($bytes), $entry['bytes']);
                $this->assertSame(hash('sha256', $bytes), $entry['sha256']);
            }
            $passport = json_decode($zip->getFromName('passport.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($passport['reference'], $manifest['reference']);
            $this->assertSame($passport['captured_at'], $manifest['captured_at']);
        } finally {
            $zip->close();
            unlink($path);
        }
    }
}
