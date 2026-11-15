<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRecentAdminAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        if (time() - (int) $request->session()->get('platform_admin_confirmed_at', 0) > config('platform.recent_auth_seconds')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Reconfirmă parola din pagina Securitate și încearcă din nou.'], 423)
                : redirect()->route('admin.settings', ['tab' => 'security'])->withErrors(['auth' => 'Reconfirmă parola înainte de a modifica setările.']);
        }
        return $next($request);
    }
}
