<?php

namespace App\Http\Middleware;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Http\Request;

class SiteSettingsContext
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(SiteSettings::class);
        $settings->reset();
        try {
            return $next($request);
        } finally {
            $settings->reset();
        }
    }
}
