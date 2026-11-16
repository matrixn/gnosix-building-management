<?php

use App\Http\Middleware\CustomerLocale;
use App\Http\Middleware\CustomerOnboarded;
use App\Http\Middleware\CustomerSession;
use App\Http\Middleware\OrganizationContext;
use App\Http\Middleware\PlatformAdminSession;
use App\Http\Middleware\PublicLocale;
use App\Http\Middleware\RequirePlatformAdmin;
use App\Http\Middleware\RequireRecentAdminAuthentication;
use App\Http\Middleware\ResponseHeaders;
use App\Http\Middleware\SiteSettingsContext;
use App\Support\CustomerIdentity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(PlatformAdminSession::class);
        $middleware->prepend(PublicLocale::class);
        $middleware->prepend(SiteSettingsContext::class);
        $middleware->trimStrings(except: ['smtp_password']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route(CustomerIdentity::destination($request->user('web'))));
        $middleware->alias([
            'platform.admin' => RequirePlatformAdmin::class,
            'platform.recent' => RequireRecentAdminAuthentication::class,
            'customer.locale' => CustomerLocale::class,
            'customer.session' => CustomerSession::class,
            'customer.onboarded' => CustomerOnboarded::class,
            'customer.organization' => OrganizationContext::class,
        ]);
        $middleware->append(ResponseHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'invitation_token', 'smtp_password', 'api_key']);
        $exceptions->respond(function (Response $response) {
            if (request()->is('app', 'app/*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }

            return $response;
        });
    })->create();
