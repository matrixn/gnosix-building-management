<?php

namespace Tests\Feature;

use App\Dna\Relations;
use App\Evidence\Engine;
use App\Jobs\GenerateOperationalReport;
use App\Jobs\SendOperationsDigest;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Evidence\Conflict;
use App\Models\MaintenanceIssue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\OperationsDigest;
use App\Operations\Metrics;
use App\Operations\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HospitalityOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'owner', string $access = 'all'): array
    {
        $user = User::factory()->create(['profile_type' => 'manager', 'onboarding_completed_at' => now()]);
        $organization = Organization::create(['name' => 'Atlas Hospitality']);
        $organization->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $member = $organization->memberships()->create(['user_id' => $user->id, 'role' => $role, 'access_scope' => $access]);
        [$property, $building] = $this->property($organization, 'North');
        $this->login($user);

        return [$user, $organization, $property, $building, $member];
    }

    private function property(Organization $organization, string $name): array
    {
        $property = $organization->properties()->create(['name' => $name, 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);

        return [$property, $property->buildings()->create(['name' => $name.' pavilion', 'state' => 'active'])];
    }

    private function login(User $user): void
    {
        $this->actingAs($user, 'web')->withSession(['customer_auth_version' => $user->auth_version, 'customer_locale' => 'en']);
    }

    private function work(Property $p, Building $b, array $data = []): WorkOrder
    {
        return WorkOrder::create(array_replace([
            'organization_id' => $p->organization_id, 'property_id' => $p->id, 'building_id' => $b->id,
            'title' => 'Valve intervention', 'category' => 'leak', 'priority' => 'critical', 'state' => 'reported',
        ], $data));
    }

    private function asset(Property $p, Building $b, string $name = 'Water loop'): Asset
    {
        return Asset::create([
            'name' => $name, 'organization_id' => $p->organization_id, 'property_id' => $p->id, 'building_id' => $b->id,
            'asset_type_id' => AssetType::where('key', 'hvac')->value('id'), 'is_system' => true, 'manual_data' => [],
        ]);
    }

    public function test_empty_scope_and_critical_work_with_drill_down(): void
    {
        [, $organization, $p, $b] = $this->fixture();
        $url = route('app.operations.index', $organization);
        $this->get($url)->assertOk()->assertSee('Operations Center')->assertSee('There are no records');
        $work = $this->work($p, $b, ['due_at' => now()->subHour()]);
        MaintenanceIssue::create(['organization_id' => $organization->id, 'property_id' => $p->id, 'building_id' => $b->id, 'title' => 'Critical leak', 'category' => 'leak', 'severity' => 'critical', 'state' => 'reported']);

        $page = $this->get($url.'?building='.$b->id.'&metric=priority')->assertOk()
            ->assertSee('Assign work order')->assertSee(route('app.maintenance.work.edit', [$organization, $work->uuid]));
        $this->assertSame(1, $page->viewData('counts')['critical']);
        $this->assertSame(1, $page->viewData('counts')['critical_overdue']);
    }

    public function test_mixed_grants_cost_restriction_and_scope_validation(): void
    {
        [, $organization, $p, $b, $member] = $this->fixture('manager', 'properties');
        [$private, $privateBuilding] = $this->property($organization, 'Restricted');
        [$viewer, $viewerBuilding] = $this->property($organization, 'Read only');
        $member->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'editor']);
        $member->propertyAccesses()->create(['property_id' => $viewer->id, 'permission' => 'viewer']);
        $group = $organization->groups()->create(['name' => 'Resort Portfolio', 'kind' => 'portfolio']);
        $group->properties()->attach([$p->id, $private->id, $viewer->id]);

        $this->work($p, $b, ['state' => 'resolved', 'resolved_at' => now()->subHour(), 'currency' => 'EUR', 'labor_cost' => 55]);
        $this->work($p, $b, ['state' => 'resolved', 'resolved_at' => now()->subHour(), 'currency' => 'RON', 'materials_cost' => 12]);
        $this->work($private, $privateBuilding, ['state' => 'resolved', 'resolved_at' => now()->subHour(), 'currency' => 'USD', 'labor_cost' => 800, 'title' => 'Secret unrelated work']);
        $this->work($viewer, $viewerBuilding, ['state' => 'resolved', 'resolved_at' => now()->subHour(), 'currency' => 'GBP', 'labor_cost' => 900]);

        $url = route('app.operations.index', [$organization, 'group' => $group->id, 'metric' => 'completed']);
        $result = $this->get($url)->assertOk()->assertDontSee('Secret unrelated work')->assertSee('Known costs by currency')->assertSee('Unknown')->assertDontSee('USD')->assertDontSee('GBP');
        $this->assertSame(3, $result->viewData('counts')['completed']);
        $this->assertSame(['EUR', 'RON'], $result->viewData('cost')['rows']->pluck('currency')->all());
        $this->get(route('app.operations.index', [$organization, 'building' => $privateBuilding->id]))->assertNotFound();
        $this->get(route('app.operations.index', [$organization, 'property' => $viewer->id, 'building' => $b->id]))->assertNotFound();
        $outsider = Organization::create(['name' => 'Foreign']);
        $this->get(route('app.operations.index', $outsider))->assertForbidden();
    }

    public function test_shared_system_simulation_recurring_work_and_evidence(): void
    {
        [$user, $organization, $p, $b] = $this->fixture();
        $wing = $p->buildings()->create(['name' => 'Second pavilion', 'state' => 'active']);
        $space = $p->spaces()->create(['name' => 'Guest room', 'space_type' => 'room', 'building_id' => $wing->id]);
        $asset = $this->asset($p, $b);
        app(Relations::class)->create($user, $p, ['source' => 'asset:'.$asset->uuid, 'target' => 'space:'.$space->uuid, 'type_key' => 'serves', 'declaration' => 'Documented shared hot water', 'proposed' => false]);
        for ($i = 0; $i < 3; $i++) {
            $this->work($p, $b, ['asset_id' => $asset->id, 'state' => 'resolved', 'resolved_at' => now()->subDays(2)]);
        }
        $page = $this->get(route('app.operations.index', [$organization, 'building' => $wing->id, 'asset' => $asset->id, 'simulate' => 1]))->assertOk()
            ->assertSee('Potentially affected spaces')->assertSee('Guest room')->assertSee('History and comparison');
        $this->assertSame(1, $page->viewData('counts')['repeated']);
        $this->assertSame(0, $page->viewData('counts')['observed_spaces']);
        $this->assertTrue($page->viewData('impact')['unknown_coverage']);

        app(Engine::class)->propose($user, $wing, ['semantic_key' => 'equipment.installation_date', 'subject_type' => 'equipment', 'subject_reference' => 'Loop', 'value' => '2022-01-01']);
        app(Engine::class)->propose($user, $wing, ['semantic_key' => 'equipment.installation_date', 'subject_type' => 'equipment', 'subject_reference' => 'Loop', 'value' => '2024-01-01']);
        $this->assertGreaterThan(0, Conflict::where('building_id', $wing->id)->count());
        $this->get(route('app.operations.index', [$organization, 'building' => $wing->id, 'metric' => 'conflicts']))->assertOk()->assertSee('Open evidence conflicts');
    }

    public function test_reports_are_queued_private_and_recheck_grants_after_generation(): void
    {
        Storage::fake('operations');
        Queue::fake();
        [$user, $organization, $p, $b, $membership] = $this->fixture('manager', 'properties');
        $membership->propertyAccesses()->create(['property_id' => $p->id, 'permission' => 'editor']);
        $asset = $this->asset($p, $b);
        $this->work($p, $b, ['asset_id' => $asset->id, 'state' => 'resolved', 'resolved_at' => now()->subHour(), 'currency' => 'EUR', 'labor_cost' => 15]);
        $this->post(route('app.operations.export', $organization), ['type' => 'asset_cost'])->assertRedirect();
        $id = DB::table('operational_reports')->value('id');
        Queue::assertPushed(GenerateOperationalReport::class, fn ($job) => $job->report === $id);
        app(Reports::class)->generate($id);
        Storage::disk('operations')->assertExists($id.'.pdf');
        $this->assertStringStartsWith('%PDF-', Storage::disk('operations')->get($id.'.pdf'));
        $url = route('app.operations.download', [$organization, $id]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->login(User::factory()->create());
        $this->get($url)->assertNotFound();
        $this->login($user);
        $membership->propertyAccesses()->delete();
        $this->get($url)->assertForbidden();
    }

    public function test_digest_is_opt_in_and_at_most_once_per_week(): void
    {
        Notification::fake();
        [$user, $organization] = $this->fixture();
        $this->post(route('app.operations.preferences', $organization), ['layout' => 'explorer', 'weekly_digest' => 1])->assertRedirect();
        $job = new SendOperationsDigest($organization->id, $user->id, CarbonImmutable::now('Europe/Bucharest')->startOfWeek()->toDateString());
        $job->handle(app(Metrics::class));
        $job->handle(app(Metrics::class));
        Notification::assertSentToTimes($user, OperationsDigest::class, 1);
    }

    public function test_large_scope_paginates_and_does_not_load_the_inventory(): void
    {
        [, $organization, $p, $b] = $this->fixture();
        for ($batch = 0; $batch < 20; $batch++) {
            DB::table('work_orders')->insert(array_map(fn ($i) => [
                'uuid' => (string) Str::uuid(), 'reference' => 'WO-LARGE-'.$i, 'title' => 'Intervention '.$i,
                'organization_id' => $organization->id, 'property_id' => $p->id, 'building_id' => $b->id,
                'category' => 'leak', 'priority' => 'critical', 'state' => 'reported',
            ], range($batch * 100, $batch * 100 + 99)));
        }
        DB::enableQueryLog();
        $page = $this->get(route('app.operations.index', [$organization, 'metric' => 'unassigned']))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(2000, $page->viewData('records')->total());
        $this->assertCount(20, $page->viewData('records'));
        $this->assertLessThan(160, $queries);
    }
}
