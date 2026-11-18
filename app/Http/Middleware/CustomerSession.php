<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('web');
        if (! $user || $user->isArchived() || (int) $request->session()->get('customer_auth_version', 0) !== $user->auth_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('status', __('identity.session_expired'));
        }
        return $next($request);
    }
}
