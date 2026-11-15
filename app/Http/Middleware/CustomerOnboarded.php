<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CustomerOnboarded
{
    public function handle(Request $request, Closure $next)
    {
        return ($request->user('web')->onboarding_completed_at || $request->user('web')->memberships()->exists()) ? $next($request) : redirect()->route('app.onboarding');
    }
}
