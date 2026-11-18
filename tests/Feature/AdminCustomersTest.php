<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminCustomersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $recent = true): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrCreate(['email' => 'platform@example.test'], ['name' => 'Operator', 'password' => 'Admin-Password-2026!']);
        $this->actingAs($admin, 'platform_admin')->withSession(['platform_admin_version' => $admin->auth_version, 'platform_admin_confirmed_at' => $recent ? time() : 0]);

        return $admin;
    }

    public function test_platform_can_create_edit_archive_and_restore_a_customer(): void
    {
        $this->getJson('/admin/customers')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'web')->getJson('/admin/customers')->assertUnauthorized();
        auth('web')->forgetUser();

        $this->admin(false);
        $input = ['name' => 'Carmen Client', 'email' => 'Carmen@example.test', 'locale' => 'en', 'password' => 'Long-customer-password!', 'password_confirmation' => 'Long-customer-password!'];
        $this->postJson('/admin/customers', $input)->assertStatus(423);
        $this->admin();
        $this->get('/admin/customers/create')->assertOk()->assertSee('Creează contul');
        $this->post('/admin/customers', $input)->assertSessionHasNoErrors();
        $customer = User::where('email', 'carmen@example.test')->firstOrFail();
        $this->assertSame('en', $customer->locale);
        $this->assertTrue(Hash::check($input['password'], $customer->password));
        $this->assertNull($customer->email_verified_at);
        $customer->markEmailAsVerified();
        $token = Password::broker('users')->createToken($customer);
        $this->assertTrue(Password::broker('users')->tokenExists($customer, $token));
        $this->get('/admin/customers?q=carmen&state=active')->assertOk()->assertSee('Vezi detalii')->assertSee('Carmen Client');
        $this->get('/admin/customers/'.$customer->id.'/edit')->assertOk()->assertDontSee($input['password']);
        $this->patch('/admin/customers/'.$customer->id, ['name' => 'Carmen Editat', 'email' => 'new@example.test', 'locale' => 'ro'])->assertSessionHasNoErrors();

        $updated = $customer->fresh();
        $this->assertNull($updated->email_verified_at);
        $this->assertFalse(Password::broker('users')->tokenExists($customer, $token));
        $this->assertGreaterThan($customer->auth_version, $updated->auth_version);
        $this->assertTrue(Hash::check($input['password'], $updated->password));

        $org = Organization::create(['name' => 'Retained organization']);
        $org->memberships()->create(['user_id' => $customer->id, 'role' => 'owner']);
        $this->deleteJson('/admin/customers/'.$customer->id)->assertUnprocessable()->assertJsonValidationErrors('confirm_archive');
        $this->delete('/admin/customers/'.$customer->id, ['confirm_archive' => 1])->assertRedirect();
        $this->assertTrue($customer->fresh()->isArchived());
        $this->assertDatabaseHas('organization_memberships', ['organization_id' => $org->id, 'user_id' => $customer->id]);
        $this->get('/admin/customers?state=archived')->assertOk()->assertSee('Carmen Editat');

        auth('web')->forgetUser();
        $this->postJson('/app/login', ['email' => $updated->email, 'password' => $input['password']])->assertUnprocessable();
        $this->actingAs($updated->fresh(), 'web')->withSession(['customer_auth_version' => $updated->auth_version])->get('/app/verify-email')->assertRedirect(route('login'));
        Notification::fake();
        $this->post('/app/forgot-password', ['email' => $updated->email])->assertRedirect();
        Notification::assertNothingSent();

        $this->admin();
        $this->post('/admin/customers/'.$customer->id.'/restore')->assertRedirect();
        $this->assertFalse($customer->fresh()->isArchived());
        $this->assertDatabaseCount('customer_admin_events', 4);
        $this->assertStringNotContainsString($input['password'], DB::table('customer_admin_events')->where('user_id', $customer->id)->pluck('fields')->implode(','));
    }

    public function test_detail_counts_only_assigned_properties_and_distinguishes_author(): void
    {
        $customer = User::factory()->create(['profile_type' => 'manager']);
        $org = Organization::create(['name' => 'Restricted organization']);
        $org->profile()->create(['profile_key' => 'hospitality', 'organization_kind' => 'company', 'schema_version' => 1]);
        $membership = $org->memberships()->create(['user_id' => $customer->id, 'role' => 'member', 'access_scope' => 'properties']);
        $visible = $org->properties()->create(['name' => 'Visible property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $hidden = $org->properties()->create(['name' => 'Other property', 'property_type' => 'hotel', 'country' => 'RO', 'timezone' => 'Europe/Bucharest']);
        $membership->propertyAccesses()->create(['property_id' => $visible->id, 'permission' => 'viewer']);
        $a = $visible->buildings()->create(['name' => 'Visible building', 'state' => 'active']);
        $visible->buildings()->create(['name' => 'Archived building', 'state' => 'archived']);
        $hidden->buildings()->create(['name' => 'Other building', 'state' => 'active']);
        $visible->spaces()->create(['name' => 'Room', 'building_id' => $a->id, 'space_type' => 'room']);
        DB::table('building_revisions')->where('building_id', $a->id)->update(['actor_id' => $customer->id]);
        $this->admin(false);

        $page = $this->get('/admin/customers/'.$customer->id)->assertOk()->assertSee('Restricted organization')->assertSee('Clădiri create de utilizator')->assertSee('Sumar operațional');
        $metrics = $page->viewData('metrics');
        foreach (['properties', 'buildings', 'created_buildings', 'spaces'] as $metric) {
            $this->assertSame(1, (int) $metrics[$metric]->get($org->id));
        }
    }
}
