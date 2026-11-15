<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\PlatformAdminSession::class);
        $middleware->prepend(\App\Http\Middleware\PublicLocale::class);
        $middleware->prepend(\App\Http\Middleware\SiteSettingsContext::class);
        $middleware->trimStrings(except: ['smtp_password']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $request) => route(\App\Support\CustomerIdentity::destination($request->user('web'))));
        $middleware->alias([
            'platform.admin' => \App\Http\Middleware\RequirePlatformAdmin::class,
            'platform.recent' => \App\Http\Middleware\RequireRecentAdminAuthentication::class,
            'customer.locale' => \App\Http\Middleware\CustomerLocale::class,
            'customer.session' => \App\Http\Middleware\CustomerSession::class,
            'customer.onboarded' => \App\Http\Middleware\CustomerOnboarded::class,
        ]);
        $middleware->append(\App\Http\Middleware\ResponseHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'smtp_password']);
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            if (request()->is('app', 'app/*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }
            return $response;
        });
    })->create();
