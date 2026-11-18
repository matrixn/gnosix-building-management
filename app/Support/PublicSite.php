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
        if (($locale ?? self::locale()) === 'en') {
            return route('home.en');
        }

        return route(request()->routeIs('home.ro') ? 'home.ro' : 'home');
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
        return $privacy ? ($locale === 'en' ? '/en/privacy' : '/confidentialitate') : ($locale === 'en' ? '/en' : '/ro');
    }

    public static function defaultPath(bool $privacy = false): string
    {
        return $privacy ? self::path('ro', true) : '/';
    }

    public static function canonical(?string $locale = null): string
    {
        return SiteSettings::value('canonical_url').self::path($locale ?? self::locale(), request()->routeIs('privacy', 'privacy.en'));
    }
}
