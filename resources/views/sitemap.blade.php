<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
    @foreach ([false, true] as $privacy)
        @foreach (['ro', 'en'] as $locale)
        <url>
            <loc>{{ config('gnosix.canonical_url').\App\Support\PublicSite::path($locale, $privacy) }}</loc>
            @foreach (['ro', 'en', 'x-default'] as $alternate)<xhtml:link rel="alternate" hreflang="{{ $alternate }}" href="{{ config('gnosix.canonical_url').\App\Support\PublicSite::path($alternate === 'x-default' ? 'ro' : $alternate, $privacy) }}"/>@endforeach
        </url>
        @endforeach
    @endforeach
</urlset>
