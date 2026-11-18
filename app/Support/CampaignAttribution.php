<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CampaignAttribution
{
    private const SESSION_KEY = 'campaign_attribution';

    private const TAGS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    public function observe(Request $request): void
    {
        if ($request->headers->get('Sec-GPC') === '1' || $request->headers->get('DNT') === '1') {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        if (! $request->isMethod('GET') || preg_match('/prefetch|prerender/i', $request->header('Sec-Purpose', '').' '.$request->header('Purpose', ''))) {
            return;
        }

        $tags = [];
        $query = $request->query->all();
        foreach (self::TAGS as $tag) {
            if (! array_key_exists($tag, $query)) {
                continue;
            }

            $value = $query[$tag];
            if (! is_string($value) || strlen($value) > 120 || ! preg_match('/\A[\p{L}\p{N}][\p{L}\p{N} ._-]*\z/u', trim($value))) {
                return;
            }
            $tags[$tag] = trim($value);
        }

        if (! isset($tags['utm_campaign'])) {
            return;
        }

        $path = $request->routeIs('home.en') ? '/en' : '/';
        $fingerprint = hash('sha256', json_encode([$path, $tags], JSON_THROW_ON_ERROR));
        $previous = $request->session()->get(self::SESSION_KEY);
        $now = now()->startOfSecond();
        if (($previous['fingerprint'] ?? null) === $fingerprint && $previous['expires'] > $now->timestamp) {
            return;
        }

        // A new tagged landing replaces the previous campaign for attribution in this session.
        $request->session()->forget(self::SESSION_KEY);
        try {
            $id = (string) Str::uuid();
            $expires = $now->copy()->addHours(2);
            DB::transaction(function () use ($id, $path, $fingerprint, $tags, $now, $expires) {
                DB::table('campaign_links')->insertOrIgnore([
                    'fingerprint' => $fingerprint,
                    'path' => $path,
                    'tags' => json_encode($tags, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'last_seen_at' => $now,
                ]);
                $link = DB::table('campaign_links')->where('fingerprint', $fingerprint)->first(['id']);
                DB::table('campaign_visits')->insert(['id' => $id, 'campaign_link_id' => $link->id, 'expires_at' => $expires]);
                DB::table('campaign_links')->where('id', $link->id)->increment('visits', 1, ['last_seen_at' => $now]);
            }, 3);
            $request->session()->put(self::SESSION_KEY, ['id' => $id, 'fingerprint' => $fingerprint, 'expires' => $expires->timestamp]);
        } catch (Throwable $exception) {
            $this->logFailure($exception);
        }
    }

    public function contact(Request $request): void
    {
        $this->convert($request, 'contacted', 'contacts');
    }

    public function registration(Request $request): void
    {
        $this->convert($request, 'registered', 'registrations');
    }

    private function convert(Request $request, string $flag, string $counter): void
    {
        if ($request->header('Sec-GPC') === '1' || $request->header('DNT') === '1') {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }
        $touch = $request->session()->get(self::SESSION_KEY);
        if (! $touch || $touch['expires'] <= now()->timestamp) {
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
                if (! $visit->contacted && ! $visit->registered) {
                    $increments['converted_visits'] = 1;
                }
                DB::table('campaign_links')->where('id', $visit->campaign_link_id)->incrementEach($increments);
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
