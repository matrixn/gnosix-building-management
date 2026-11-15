<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Support\CustomerIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        Gate::forUser($request->user('web'))->authorize('update', $request->user('web'));
        return view('identity.account');
    }

    public function update(Request $request)
    {
        $user = $request->user('web');
        Gate::forUser($user)->authorize('update', $user);
        $request->merge(['email' => is_string($request->input('email')) ? strtolower($request->input('email')) : $request->input('email')]);
        $changed = $request->input('email') !== $user->email;
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)],
            'locale' => ['required', 'in:ro,en'],
            'current_password' => $changed ? ['required', 'string', 'current_password:web'] : ['nullable', 'string'],
        ]);
        $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'locale' => $data['locale']]);
        if ($changed) {
            $user->email_verified_at = null;
        }
        $user->save();
        $request->session()->put('customer_locale', $data['locale']);
        app()->setLocale($data['locale']);
        if ($changed) {
            CustomerIdentity::rotate($request, $user);
            CustomerIdentity::verifyEmail($user);
            return redirect()->route('verification.notice')->with('status', __('identity.email_changed'));
        }
        return redirect()->route('app.account')->with('status', __('identity.profile_saved'));
    }

    public function password(Request $request)
    {
        $user = $request->user('web');
        Gate::forUser($user)->authorize('update', $user);
        $data = $request->validate(['current_password' => ['required', 'string', 'current_password:web'], 'password' => CustomerIdentity::passwordRules()]);
        $user->forceFill(['password' => $data['password']])->save();
        CustomerIdentity::rotate($request, $user);
        return redirect()->route('app.account')->with('status', __('identity.password_updated'));
    }

    public function sessions(Request $request)
    {
        $request->validate(['current_password' => ['required', 'string', 'current_password:web']]);
        CustomerIdentity::rotate($request, $request->user('web'));
        return redirect()->route('app.account')->with('status', __('identity.sessions_revoked'));
    }
}
