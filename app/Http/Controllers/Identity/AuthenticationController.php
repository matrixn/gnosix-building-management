<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CampaignAttribution;
use App\Support\CustomerIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticationController extends Controller
{
    public function login()
    {
        return view('identity.login');
    }

    public function authenticate(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255'], 'password' => ['required', 'string', 'max:255']]);
        if (! Auth::guard('web')->attempt(['email' => strtolower($data['email']), 'password' => $data['password'], 'archived_at' => null], false)) {
            throw ValidationException::withMessages(['email' => __('identity.credentials_invalid')]);
        }
        $user = Auth::guard('web')->user();
        $verification = CustomerIdentity::intendedVerification($request->session()->get('url.intended'), $user);
        CustomerIdentity::establish($request, $user);
        if ($verification) return redirect($verification);
        return redirect()->route(CustomerIdentity::destination($user));
    }

    public function register()
    {
        return view('identity.register');
    }

    public function store(Request $request)
    {
        $request->merge(['email' => is_string($request->input('email')) ? strtolower($request->input('email')) : $request->input('email')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => CustomerIdentity::passwordRules(),
            'privacy' => ['accepted'],
        ]);
        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->locale = app()->getLocale();
        $user->save();
        app(CampaignAttribution::class)->registration($request);
        CustomerIdentity::establish($request, $user);
        $sent = CustomerIdentity::verifyEmail($user);
        return redirect()->route('verification.notice')->with('delivery_warning', ! $sent)->with('status', __($sent ? 'identity.verification_sent' : 'identity.mail_unavailable'));
    }

    public function destroy(Request $request)
    {
        $locale = app()->getLocale();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login', ['lang' => $locale])->with('status', __('identity.signed_out'));
    }
}
