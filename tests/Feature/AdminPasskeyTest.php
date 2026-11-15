<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\VirtualPasskey;
use Tests\TestCase;

class AdminPasskeyTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $admin;
    private string $origin = 'https://gnosix.zion3d.ro';

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.passkey_rp_id' => 'gnosix.zion3d.ro', 'passkeys.relying_party.id' => 'gnosix.zion3d.ro', 'platform.passkey_origin' => $this->origin]);
        $this->admin = PlatformAdmin::create(['name' => 'Demo', 'email' => 'admin@example.test', 'password' => 'Synthetic-password-123']);
    }

    private function enroll(): VirtualPasskey
    {
        $this->actingAs($this->admin, 'platform_admin')->withSession(['platform_admin_version' => $this->admin->auth_version, 'platform_admin_confirmed_at' => time()]);
        $options = $this->postJson('/admin/passkeys/register-options')->assertOk()->json();
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $key = new VirtualPasskey;
        $this->postJson('/admin/passkeys', ['name' => 'Synthetic authenticator', 'credential' => $key->registration($options, $this->origin)])->assertOk();
        $this->assertDatabaseCount('platform_admin_passkeys', 1);
        $this->post('/admin/logout')->assertRedirect();
        Auth::forgetGuards();
        return $key;
    }

    public function test_signed_passkey_registration_and_login_are_real_and_single_use(): void
    {
        $key = $this->enroll();
        $options = $this->postJson('/admin/passkeys/options')->assertOk()->json();
        $payload = ['credential' => $key->assertion($options, $this->origin, $this->admin->getPasskeyId())];
        $this->postJson('/admin/passkeys/login', $payload)->assertOk()->assertJsonPath('redirect', route('admin.settings'));
        $this->assertAuthenticatedAs($this->admin, 'platform_admin');
        $this->assertGuest('web');
        $this->assertNotNull($this->admin->passkeys()->first()->last_used_at);
        $this->postJson('/admin/passkeys/login', $payload)->assertUnprocessable();
    }

    public function test_wrong_origin_rp_challenge_and_missing_user_verification_are_rejected(): void
    {
        $key = $this->enroll();
        foreach (['origin', 'rp', 'challenge', 'uv', 'signature', 'handle'] as $failure) {
            $options = $this->postJson('/admin/passkeys/options')->assertOk()->json();
            $usedOptions = $options;
            if ($failure === 'challenge') $usedOptions['challenge'] = VirtualPasskey::encode(random_bytes(32));
            $credential = $key->assertion($usedOptions, $failure === 'origin' ? 'https://other.example.test' : $this->origin, $failure === 'handle' ? 'platform-admin:999' : $this->admin->getPasskeyId(), 1, $failure !== 'uv', $failure === 'rp' ? 'other.example.test' : null);
            if ($failure === 'signature') $credential['response']['signature'] = VirtualPasskey::encode(random_bytes(64));
            $this->postJson('/admin/passkeys/login', ['credential' => $credential])->assertUnprocessable();
            $this->assertGuest('platform_admin');
        }
    }

    public function test_expired_and_revoked_passkeys_cannot_authenticate(): void
    {
        $key = $this->enroll();
        $options = $this->postJson('/admin/passkeys/options')->assertOk()->json();
        $this->withSession(['admin-passkey-login' => ['options' => json_encode($options), 'expires' => time() - 1]])
            ->postJson('/admin/passkeys/login', ['credential' => $key->assertion($options, $this->origin, $this->admin->getPasskeyId())])->assertUnprocessable();
        $options = $this->postJson('/admin/passkeys/options')->assertOk()->json();
        $this->admin->passkeys()->delete();
        $this->postJson('/admin/passkeys/login', ['credential' => $key->assertion($options, $this->origin, $this->admin->getPasskeyId())])->assertUnprocessable();
        $this->assertGuest('platform_admin');
    }

    public function test_registration_requires_admin_and_passkeys_are_owned_by_their_admin(): void
    {
        $this->postJson('/admin/passkeys/register-options')->assertUnauthorized();
        $this->enroll();
        $other = PlatformAdmin::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'Synthetic-password-456']);
        $id = $this->admin->passkeys()->first()->id;
        $this->actingAs($other, 'platform_admin')->withSession(['platform_admin_version' => $other->auth_version, 'platform_admin_confirmed_at' => time()]);
        $this->delete('/admin/passkeys/'.$id)->assertNotFound();
        $this->assertDatabaseCount('platform_admin_passkeys', 1);
    }
}
