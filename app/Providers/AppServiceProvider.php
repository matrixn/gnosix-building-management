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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('pilot', fn (Request $request) => [
            Limit::perMinute(5)->by('pilot-minute:'.$request->ip()),
            Limit::perDay(20)->by('pilot-day:'.$request->ip()),
        ]);
    }
}
