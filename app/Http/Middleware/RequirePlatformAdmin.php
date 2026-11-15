<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequirePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('platform_admin')->user();
        if (! $admin || $request->session()->get('platform_admin_version') !== $admin->auth_version) {
            Auth::guard('platform_admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $request->expectsJson() ? response()->json(['message' => 'Autentifică-te din nou.'], 401) : redirect()->route('admin.login');
        }
        return $next($request);
    }
}
