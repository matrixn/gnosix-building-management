<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Support\SiteSettings::class);
        $this->app->extend('mail.manager', fn ($manager, $app) => new \App\Mail\PlatformMailManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('platform-login', fn (Request $request) => [
            Limit::perMinute(10)->by('admin-ip:'.$request->ip()),
            Limit::perMinute(5)->by('admin-account:'.hash('sha256', strtolower((string) $request->input('email')))),
        ]);
        RateLimiter::for('platform-passkey', fn (Request $request) => Limit::perMinute(20)->by('admin-passkey:'.$request->ip()));
        RateLimiter::for('platform-sensitive', fn (Request $request) => Limit::perMinute(10)->by('admin-sensitive:'.$request->ip()));
        RateLimiter::for('platform-test-mail', fn (Request $request) => Limit::perMinute(3)->by('admin-mail:'.$request->ip()));
        RateLimiter::for('pilot', fn (Request $request) => [
            Limit::perMinute(5)->by('pilot-minute:'.$request->ip()),
            Limit::perDay(20)->by('pilot-day:'.$request->ip()),
        ]);
    }
}
