<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CustomerLocale
{
    public function handle(Request $request, Closure $next)
    {
        $selected = $request->query('lang');
        $locale = in_array($selected, ['ro', 'en'], true) ? $selected : $request->session()->get('customer_locale', $request->user('web')?->preferredLocale() ?? 'ro');
        app()->setLocale(in_array($locale, ['ro', 'en'], true) ? $locale : 'ro');
        $request->session()->put('customer_locale', app()->getLocale());
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
