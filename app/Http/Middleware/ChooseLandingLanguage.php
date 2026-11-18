<?php

namespace App\Http\Middleware;

use App\Support\LandingCountry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ChooseLandingLanguage
{
    private const PREFERENCE = 'landing_language';

    public function __construct(private LandingCountry $country) {}

    public function handle(Request $request, Closure $next): Response
    {
        $explicit = $request->query('lang');
        $root = $request->routeIs('home');

        $choice = match (true) {
            $request->routeIs('home.ro') => 'ro',
            $explicit === 'en' => 'en',
            $root && $explicit === 'ro' => 'ro',
            default => null,
        };
        if ($request->isMethod('GET') && $choice !== null) {
            $request->session()->put(self::PREFERENCE, $choice);
        }

        if ($root) {
            $preference = $choice ?? $request->session()->get(self::PREFERENCE);
            $english = $preference === 'en' || ($preference !== 'ro' && $this->country->isForeign($request->ip()));
            if ($english) {
                $query = http_build_query($request->query(), '', '&', PHP_QUERY_RFC3986);

                return new RedirectResponse('/en'.($query === '' ? '' : '?'.$query), 302, ['Cache-Control' => 'private, no-store']);
            }
        }

        $response = $next($request);
        if ($root || $choice !== null) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
