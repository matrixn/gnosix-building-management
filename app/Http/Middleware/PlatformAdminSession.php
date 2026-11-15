<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PlatformAdminSession
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('admin', 'admin/*')) {
            return $next($request);
        }
        $original = config()->get('session');
        config(['session.cookie' => 'gnosix_platform_session', 'session.path' => '/admin']);
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            return $response;
        } finally {
            config(['session' => $original]);
        }
    }
}
