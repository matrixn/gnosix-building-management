<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Support\Serializer;
use Throwable;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialDescriptor;

class PasskeyController extends Controller
{
    public function registrationOptions(Request $request, GeneratePasskeyRegisterOptionsAction $action)
    {
        $options = $action->execute(Auth::guard('platform_admin')->user(), false);
        $options->challenge = random_bytes(32);
        $options->timeout = 120000;
        $options->pubKeyCredParams = [new PublicKeyCredentialParameters('public-key', -7), new PublicKeyCredentialParameters('public-key', -257)];
        $options->excludeCredentials = Auth::guard('platform_admin')->user()->passkeys()->get()->map(fn ($key) => new PublicKeyCredentialDescriptor('public-key', $key->data->publicKeyCredentialId))->all();
        $options->authenticatorSelection->userVerification = AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED;
        $json = Serializer::make()->toJson($options);
        $request->session()->put('admin-passkey-register', ['options' => $json, 'expires' => time() + 120, 'admin' => Auth::guard('platform_admin')->id()]);
        return response($json, 200, ['Content-Type' => 'application/json']);
    }

    public function register(Request $request, StorePasskeyAction $action)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'credential' => ['required', 'array']]);
        $ceremony = $request->session()->pull('admin-passkey-register');
        abort_unless($ceremony && $ceremony['expires'] >= time() && $ceremony['admin'] === Auth::guard('platform_admin')->id(), 422, 'Cererea a expirat. Încearcă din nou.');
        try {
            $action->execute(Auth::guard('platform_admin')->user(), json_encode($data['credential'], JSON_THROW_ON_ERROR), $ceremony['options'], config('platform.passkey_rp_id'), ['name' => $data['name']]);
        } catch (Throwable) {
            return response()->json(['message' => 'Passkey-ul nu a putut fi verificat sau este deja înregistrat.'], 422);
        }
        return response()->json(['message' => 'Passkey înregistrat.']);
    }

    public function authenticationOptions(Request $request)
    {
        $options = new PublicKeyCredentialRequestOptions(challenge: random_bytes(32), rpId: config('platform.passkey_rp_id'), allowCredentials: [], userVerification: 'required');
        $json = Serializer::make()->toJson($options);
        $request->session()->put('admin-passkey-login', ['options' => $json, 'expires' => time() + 120]);
        return response($json, 200, ['Content-Type' => 'application/json']);
    }

    public function authenticate(Request $request, FindPasskeyToAuthenticateAction $action)
    {
        $data = $request->validate(['credential' => ['required', 'array']]);
        $ceremony = $request->session()->pull('admin-passkey-login');
        abort_unless($ceremony && $ceremony['expires'] >= time(), 422, 'Cererea a expirat. Încearcă din nou.');
        try {
            $passkey = $action->execute(json_encode($data['credential'], JSON_THROW_ON_ERROR), $ceremony['options']);
        } catch (Throwable) {
            $passkey = null;
        }
        $admin = $passkey?->authenticatable;
        if (! $admin instanceof PlatformAdmin) {
            return response()->json(['message' => 'Passkey-ul nu a putut fi verificat.'], 422);
        }
        AuthenticationController::establish($request, $admin);
        return response()->json(['redirect' => route('admin.settings')]);
    }

    public function destroy(Request $request, int $passkey)
    {
        Auth::guard('platform_admin')->user()->passkeys()->findOrFail($passkey)->delete();
        return redirect()->route('admin.settings', ['tab' => 'security'])->with('status', 'Passkey revocat. Parola rămâne disponibilă pentru autentificare.');
    }
}
