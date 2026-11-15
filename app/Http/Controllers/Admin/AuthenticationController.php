<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticationController extends Controller
{
    public function show()
    {
        return Auth::guard('platform_admin')->check() ? redirect()->route('admin.settings') : view('admin.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255']]);
        if (! Auth::guard('platform_admin')->attempt(['email' => strtolower($data['email']), 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'Datele de autentificare nu sunt corecte.']);
        }
        self::establish($request, Auth::guard('platform_admin')->user());
        return redirect()->route('admin.settings');
    }

    public static function establish(Request $request, PlatformAdmin $admin): void
    {
        if (Auth::guard('platform_admin')->id() !== $admin->id) {
            Auth::guard('platform_admin')->login($admin);
        }
        $request->session()->regenerate();
        $request->session()->put('platform_admin_version', $admin->auth_version);
        $request->session()->put('platform_admin_confirmed_at', time());
        $request->session()->forget(['admin-passkey-register', 'admin-passkey-login']);
    }

    public function confirm(Request $request)
    {
        $request->validate(['password' => ['required', 'string', 'max:255']]);
        if (! Hash::check($request->input('password'), Auth::guard('platform_admin')->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Parola nu este corectă.']);
        }
        $request->session()->put('platform_admin_confirmed_at', time());
        return redirect()->route('admin.settings', ['tab' => 'security'])->with('status', 'Identitatea a fost reconfirmată pentru 10 minute.');
    }

    public function destroy(Request $request)
    {
        Auth::guard('platform_admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
