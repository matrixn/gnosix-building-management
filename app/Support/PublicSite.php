<?php

namespace App\Support;

class PublicSite
{
    public static function locale(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ro';
    }

    public static function home(?string $locale = null): string
    {
        return route(($locale ?? self::locale()) === 'en' ? 'home.en' : 'home');
    }

    public static function privacy(?string $locale = null): string
    {
        return route(($locale ?? self::locale()) === 'en' ? 'privacy.en' : 'privacy');
    }

    public static function contact(): string
    {
        return route(self::locale() === 'en' ? 'contact.store.en' : 'contact.store');
    }

    public static function path(string $locale, bool $privacy = false): string
    {
        return $privacy ? ($locale === 'en' ? '/en/privacy' : '/confidentialitate') : ($locale === 'en' ? '/en' : '/');
    }

    public static function canonical(?string $locale = null): string
    {
        return config('gnosix.canonical_url').self::path($locale ?? self::locale(), request()->routeIs('privacy', 'privacy.en'));
    }
}
