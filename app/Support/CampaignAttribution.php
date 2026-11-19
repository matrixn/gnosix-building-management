<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CampaignAttribution
{
    private const SESSION_KEY = 'campaign_attribution';

    private const UNTAGGED_KEY = 'untagged_attribution';

    private const SIGNUP_KEY = 'registration_entry';

    private const TAGS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    public function __construct(private LandingCountry $country) {}

    public function observe(Request $request): void
    {
        if ($request->headers->get('Sec-GPC') === '1' || $request->headers->get('DNT') === '1') {
            $request->session()->forget([self::SESSION_KEY, self::UNTAGGED_KEY, self::SIGNUP_KEY]);

            return;
        }

        if (! $request->isMethod('GET') || preg_match('/prefetch|prerender/i', $request->header('Sec-Purpose', '').' '.$request->header('Purpose', ''))) {
            return;
        }

        $now = now()->startOfSecond();
        $campaign = $request->session()->get(self::SESSION_KEY);
        $untagged = $request->session()->get(self::UNTAGGED_KEY);
        $tags = $this->tags($request);
        $path = $request->routeIs('home.en') ? '/en' : '/';
        $referrer = $this->referrer($request);
        $tagged = isset($tags['utm_campaign']);

        if (! $tagged && $referrer === '' && ($this->active($campaign, $now->timestamp) || $this->active($untagged, $now->timestamp))) {
            // A route change or missing Referer in an existing visit is not a new entry.
            return;
        }

        $sessionKey = $tagged ? self::SESSION_KEY : self::UNTAGGED_KEY;
        $previous = $tagged ? $campaign : $untagged;
        $fingerprint = $this->fingerprint($path, $tags);
        $touchKey = $tagged ? $fingerprint : $this->fingerprint($path, [$referrer]);
        if ($this->active($previous, $now->timestamp) && $previous['fingerprint'] === $touchKey) {
            return;
        }

        $request->session()->forget($sessionKey);
        try {
            $id = (string) Str::uuid();
            $expires = $now->copy()->addHours(2);
            $country = $this->country->forRequest($request);
            $day = $now->copy()->setTimezone(CampaignPeriod::TIMEZONE)->toDateString();
            DB::transaction(function () use ($id, $path, $fingerprint, $tags, $tagged, $referrer, $now, $expires, $country, $day) {
                DB::table('campaign_links')->insertOrIgnore([
                    'fingerprint' => $fingerprint,
                    'path' => $path,
                    'tags' => json_encode($tags, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'is_tagged' => $tagged,
                    'last_seen_at' => $now,
                ]);
                $link = DB::table('campaign_links')->where('fingerprint', $fingerprint)->first(['id']);
                DB::table('campaign_visits')->insert(['id' => $id, 'campaign_link_id' => $link->id, 'country_code' => $country, 'traffic_day' => $day, 'referrer_host' => $referrer, 'expires_at' => $expires]);
                DB::table('campaign_links')->where('id', $link->id)->increment('visits', 1, ['last_seen_at' => $now]);
                $source = ['campaign_link_id' => $link->id, 'host' => $referrer];
                DB::table('campaign_link_referrers')->insertOrIgnore($source);
                DB::table('campaign_link_referrers')->where($source)->increment('visits');
                $location = ['campaign_link_id' => $link->id, 'country_code' => $country];
                DB::table('campaign_link_countries')->insertOrIgnore($location);
                DB::table('campaign_link_countries')->where($location)->increment('visits');
                $daily = $location + ['traffic_day' => $day, 'referrer_host' => $referrer];
                DB::table('campaign_daily_totals')->insertOrIgnore($daily);
                DB::table('campaign_daily_totals')->where($daily)->increment('visits');
            }, 3);
            $request->session()->put($sessionKey, ['id' => $id, 'fingerprint' => $touchKey, 'expires' => $expires->timestamp]);
        } catch (Throwable $exception) {
            $this->logFailure($exception);
        }
    }

    private function active(?array $touch, int $timestamp): bool
    {
        return $touch !== null && $touch['expires'] > $timestamp;
    }

    private function fingerprint(string $path, array $dimensions): string
    {
        return hash('sha256', json_encode([$path, $dimensions], JSON_THROW_ON_ERROR));
    }

    private function tags(Request $request): array
    {
        $tags = [];
        $query = $request->query->all();
        foreach (self::TAGS as $tag) {
            if (! array_key_exists($tag, $query)) {
                continue;
            }

            $value = $query[$tag];
            if (! is_string($value) || strlen($value) > 120 || ! preg_match('/\A[\p{L}\p{N}][\p{L}\p{N} ._-]*\z/u', trim($value))) {
                return [];
            }
            $tags[$tag] = trim($value);
        }

        return isset($tags['utm_campaign']) ? $tags : [];
    }

    private function referrer(Request $request): string
    {
        $value = $request->headers->get('Referer', '');
        $url = strlen($value) <= 4096 ? parse_url($value) : false;
        if (! $url || ! in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)) {
            return '';
        }

        $host = $this->normalizeHost($url['host'] ?? '');
        $ownHost = $this->normalizeHost((string) parse_url(SiteSettings::value('canonical_url'), PHP_URL_HOST));
        if ($host === $ownHost || $host === $this->normalizeHost($request->getHost())
            || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return '';
        }

        return $host;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public function contact(Request $request): void
    {
        $this->convert($request, 'contacted', 'contacts');
    }

    public function registration(Request $request): void
    {
        $this->convert($request, 'registered', 'registrations');
    }

    public function registrationEntry(Request $request): void
    {
        if ($request->header('Sec-GPC') === '1' || $request->header('DNT') === '1') {
            $request->session()->forget([self::SESSION_KEY, self::UNTAGGED_KEY, self::SIGNUP_KEY]);

            return;
        }
        if (! $request->isMethod('GET') || preg_match('/prefetch|prerender/i', $request->header('Sec-Purpose', '').' '.$request->header('Purpose', ''))) {
            return;
        }

        $tags = $this->tags($request);
        $referrer = $this->referrer($request);
        $previous = $request->session()->get(self::SIGNUP_KEY);
        if ($tags === [] && $referrer === '' && $this->active($previous, now()->timestamp)) {
            return;
        }
        $request->session()->put(self::SIGNUP_KEY, [
            'country' => $this->country->forRequest($request), 'referrer' => $referrer, 'utm' => $tags,
            'expires' => now()->addHours(2)->timestamp,
        ]);
    }

    public function registrationSource(Request $request): ?array
    {
        if ($request->header('Sec-GPC') === '1' || $request->header('DNT') === '1') {
            return null;
        }

        $timestamp = now()->timestamp;
        $touch = $request->session()->get(self::SESSION_KEY);
        if (! $this->active($touch, $timestamp)) {
            $touch = $request->session()->get(self::UNTAGGED_KEY);
        }
        $visit = $this->active($touch, $timestamp)
            ? DB::table('campaign_visits as visit')->join('campaign_links as link', 'link.id', '=', 'visit.campaign_link_id')
                ->where('visit.id', $touch['id'])->where('visit.expires_at', '>', now())
                ->first(['visit.country_code', 'visit.referrer_host', 'link.tags'])
            : null;

        $entry = $request->session()->pull(self::SIGNUP_KEY);
        if (! $visit && $this->active($entry, $timestamp)) {
            return ['country' => $entry['country'], 'referrer' => $entry['referrer'], 'utm' => $entry['utm']];
        }

        return [
            'country' => $visit?->country_code ?? $this->country->forRequest($request),
            'referrer' => $visit?->referrer_host ?? $this->referrer($request),
            'utm' => $visit ? json_decode($visit->tags, true, flags: JSON_THROW_ON_ERROR) : [],
        ];
    }

    private function convert(Request $request, string $flag, string $counter): void
    {
        if ($request->header('Sec-GPC') === '1' || $request->header('DNT') === '1') {
            $request->session()->forget([self::SESSION_KEY, self::UNTAGGED_KEY, self::SIGNUP_KEY]);

            return;
        }
        $timestamp = now()->timestamp;
        $touch = $request->session()->get(self::SESSION_KEY);
        if (! $this->active($touch, $timestamp)) {
            $touch = $request->session()->get(self::UNTAGGED_KEY);
        }
        if (! $this->active($touch, $timestamp)) {
            return;
        }

        try {
            DB::transaction(function () use ($touch, $flag, $counter) {
                $visit = DB::table('campaign_visits')->where('id', $touch['id'])->where('expires_at', '>', now())->lockForUpdate()->first();
                if (! $visit || $visit->{$flag}) {
                    return;
                }

                DB::table('campaign_visits')->where('id', $visit->id)->update([$flag => true]);
                $increments = [$counter => 1];
                $firstConversion = ! $visit->contacted && ! $visit->registered;
                if ($firstConversion) {
                    $increments['converted_visits'] = 1;
                }
                DB::table('campaign_links')->where('id', $visit->campaign_link_id)->incrementEach($increments);
                if ($firstConversion && $visit->country_code !== null) {
                    DB::table('campaign_link_countries')->where('campaign_link_id', $visit->campaign_link_id)
                        ->where('country_code', $visit->country_code)->increment('converted_visits');
                }
                if ($visit->traffic_day !== null) {
                    DB::table('campaign_daily_totals')->where([
                        'campaign_link_id' => $visit->campaign_link_id,
                        'country_code' => $visit->country_code,
                        'traffic_day' => $visit->traffic_day,
                        'referrer_host' => $visit->referrer_host,
                    ])->incrementEach($increments);
                }
            }, 3);
        } catch (Throwable $exception) {
            $this->logFailure($exception);
        }
    }

    private function logFailure(Throwable $exception): void
    {
        // Database exceptions can include campaign tags and internal identifiers.
        logger()->warning('Campaign measurement failed.', ['type' => $exception::class]);
    }
}
