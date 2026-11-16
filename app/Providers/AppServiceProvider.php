<?php

namespace App\Providers;

use App\Evidence\Extractor;
use App\Evidence\ProviderExtractor;
use App\Mail\PlatformMailManager;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Floor;
use App\Models\MaintenanceIssue;
use App\Models\MaintenancePlan;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Models\WorkOrder;
use App\Observers\BuildingStructureObserver;
use App\Policies\AssetPolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\UserPolicy;
use App\Support\SiteSettings;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Extractor::class, ProviderExtractor::class);
        $this->app->scoped(SiteSettings::class);
        $this->app->scoped(TenantContext::class);
        $this->app->extend('mail.manager', fn ($manager, $app) => new PlatformMailManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Asset::class, AssetPolicy::class);
        foreach ([MaintenanceIssue::class, WorkOrder::class, MaintenancePlan::class] as $model) {
            Gate::policy($model, MaintenancePolicy::class);
        }
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        foreach ([Building::class, Floor::class, Space::class] as $model) {
            $model::observe(BuildingStructureObserver::class);
        }
        RateLimiter::for('customer-login', fn (Request $request) => [
            Limit::perMinute(15)->by('customer-login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('customer-login-account:'.hash('sha256', is_string($request->input('email')) ? strtolower($request->input('email')) : '')),
        ]);
        RateLimiter::for('customer-register', fn (Request $request) => [Limit::perMinute(3)->by('customer-register:'.$request->ip()), Limit::perHour(10)->by('customer-register-hour:'.$request->ip())]);
        RateLimiter::for('customer-reset', fn (Request $request) => [Limit::perMinute(5)->by('customer-reset:'.$request->ip()), Limit::perMinute(3)->by('customer-reset-account:'.hash('sha256', is_string($request->input('email')) ? strtolower($request->input('email')) : ''))]);
        RateLimiter::for('customer-verification', fn (Request $request) => Limit::perMinute(1)->by('customer-verification:'.$request->user('web')->id));
        RateLimiter::for('customer-sensitive', fn (Request $request) => Limit::perMinute(10)->by('customer-sensitive:'.$request->user('web')->id));
        RateLimiter::for('organization-draft', fn (Request $request) => Limit::perMinute(60)->by('organization-draft:'.$request->user('web')->id));
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
