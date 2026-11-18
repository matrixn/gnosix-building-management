<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CampaignPeriod;
use App\Support\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function __invoke(Request $request)
    {
        $today = CarbonImmutable::today(CampaignPeriod::TIMEZONE)->toDateString();
        $input = $request->validate([
            'q' => 'nullable|string|max:120',
            'sort' => 'nullable|in:recent,visits,conversions',
            'kind' => 'nullable|in:all,tagged,untagged',
            'period' => 'nullable|in:all,today,7d,30d,custom',
            'from' => [Rule::excludeIf($request->query('period') !== 'custom'), 'required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'to' => [Rule::excludeIf($request->query('period') !== 'custom'), 'required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$today],
        ]);
        $period = CampaignPeriod::from($input);
        $search = $input['q'] ?? '';
        $sort = $input['sort'] ?? 'recent';
        $kind = $input['kind'] ?? 'all';
        $query = $this->links($period, $kind, $search);
        $eligible = (clone $query)->select('id', 'is_tagged');

        $totals = (clone $query)->selectRaw('COUNT(*) AS links, SUM(visits) AS visits, SUM(converted_visits) AS converted_visits, SUM(contacts) AS contacts, SUM(registrations) AS registrations')->first();
        $countrySource = $period->limited() ? $period->daily() : DB::table('campaign_link_countries');
        $countries = $this->breakdown(clone $countrySource, clone $eligible, 'country_code')
            ->orderByRaw('SUM(traffic.visits) DESC')->orderBy('traffic.country_code')->get();
        $countryCoverage = (int) ($totals->visits ?? 0) - $countries->sum(fn ($country) => $country->tagged_visits + $country->untagged_visits);
        $daily = $this->breakdown($period->daily(), clone $eligible, 'traffic_day')->orderBy('traffic.traffic_day')->get();
        $series = $period->series($daily);
        $firstDailyVisit = DB::table('campaign_daily_totals')->min('traffic_day');
        $covered = $firstDailyVisit !== null && $firstDailyVisit <= $period->end->toDateString();
        $coverageStart = $covered && $firstDailyVisit > $period->start->toDateString()
            ? CarbonImmutable::parse($firstDailyVisit)->format('d.m.Y')
            : null;

        $links = $query->orderByDesc(match ($sort) {
            'visits' => 'visits',
            'conversions' => 'converted_visits',
            default => 'last_seen_at',
        })->orderByDesc('id')->paginate(25)->withQueryString();
        $baseUrl = rtrim(SiteSettings::value('canonical_url'), '/');
        $ids = $links->pluck('id')->all();
        $referrers = $this->referrers($period, $ids);
        $linkCountries = $countrySource->whereIn('campaign_link_id', $ids)
            ->select('campaign_link_id', 'country_code')->selectRaw('SUM(visits) as visits, SUM(converted_visits) as converted_visits')
            ->groupBy('campaign_link_id', 'country_code')->orderByDesc('visits')->orderBy('country_code')->get()->groupBy('campaign_link_id');
        $links->through(function ($link) use ($baseUrl, $referrers, $linkCountries) {
            $link->tags = json_decode($link->tags, true, flags: JSON_THROW_ON_ERROR);
            $link->url = $baseUrl.$link->path;
            if ($link->tags) {
                $link->url .= '?'.http_build_query($link->tags, encoding_type: PHP_QUERY_RFC3986);
            }
            $link->countries = $linkCountries->get($link->id, collect());
            $link->sources = $referrers->get($link->id, collect());
            $measured = (int) ($link->sources->first()?->measured ?? 0);
            $link->earlier_visits = max(0, $link->visits - $measured);
            $link->other_sources = max(0, $measured - $link->sources->sum('visits'));

            return $link;
        });

        return response()->view('admin.campaigns', compact('links', 'totals', 'search', 'sort', 'kind', 'countries', 'countryCoverage', 'period', 'today', 'series', 'covered', 'coverageStart', 'firstDailyVisit'))->header('Cache-Control', 'private, no-store');
    }

    private function links(CampaignPeriod $period, string $kind, string $search): Builder
    {
        $query = DB::table('campaign_links');
        if ($period->limited()) {
            $counts = $period->daily()->select('campaign_link_id')->groupBy('campaign_link_id')
                ->selectRaw('MAX(traffic_day) AS last_seen_at, SUM(visits) AS visits, SUM(converted_visits) AS converted_visits, SUM(contacts) AS contacts, SUM(registrations) AS registrations');
            $window = DB::table('campaign_links as catalog')->joinSub($counts, 'counts', 'counts.campaign_link_id', '=', 'catalog.id')
                ->select('catalog.id', 'catalog.path', 'catalog.tags', 'catalog.is_tagged', 'counts.last_seen_at', 'counts.visits', 'counts.converted_visits', 'counts.contacts', 'counts.registrations');
            $query = DB::query()->fromSub($window, 'campaign_links');
        }
        if ($kind !== 'all') {
            $query->where('is_tagged', $kind === 'tagged');
        }
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $referrers = $period->limited() ? $period->daily() : DB::table('campaign_link_referrers');
            $host = $period->limited() ? 'referrer_host' : 'host';
            $query->where(function ($query) use ($pattern, $referrers, $host) {
                $query->whereRaw("LOWER(tags) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("path LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereExists($referrers->selectRaw('1')->whereColumn('campaign_link_id', 'campaign_links.id')
                        ->whereRaw("$host LIKE LOWER(?) ESCAPE '!'", [$pattern]));
            });
        }

        return $query;
    }

    private function breakdown(Builder $rows, Builder $eligible, string $group): Builder
    {
        return DB::query()->fromSub($rows, 'traffic')
            ->joinSub($eligible, 'links', 'links.id', '=', 'traffic.campaign_link_id')
            ->select('traffic.'.$group)
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN traffic.visits ELSE 0 END) AS tagged_visits')
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN traffic.converted_visits ELSE 0 END) AS tagged_conversions')
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN 0 ELSE traffic.visits END) AS untagged_visits')
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN 0 ELSE traffic.converted_visits END) AS untagged_conversions')
            ->groupBy('traffic.'.$group);
    }

    private function referrers(CampaignPeriod $period, array $linkIds)
    {
        if ($linkIds === []) {
            return collect();
        }
        $rows = $period->limited()
            ? $period->daily()->select('campaign_link_id', 'referrer_host as host')->selectRaw('SUM(visits) AS visits')
                ->groupBy('campaign_link_id', 'referrer_host')
            : DB::table('campaign_link_referrers')->select('campaign_link_id', 'host', 'visits');
        $sources = DB::query()->fromSub($rows->whereIn('campaign_link_id', $linkIds), 'link_sources')
            ->select('campaign_link_id', 'host', 'visits')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY campaign_link_id ORDER BY visits DESC, host) AS source_rank')
            ->selectRaw('SUM(visits) OVER (PARTITION BY campaign_link_id) AS measured');

        return DB::query()->fromSub($sources, 'ranked_sources')->where('source_rank', '<=', 10)
            ->orderBy('source_rank')->get()->groupBy('campaign_link_id');
    }
}
