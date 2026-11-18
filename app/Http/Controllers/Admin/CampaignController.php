<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = $request->validate(['q' => 'nullable|string|max:120', 'sort' => 'nullable|in:recent,visits,conversions', 'kind' => 'nullable|in:all,tagged,untagged']);
        $search = $input['q'] ?? '';
        $sort = $input['sort'] ?? 'recent';
        $kind = $input['kind'] ?? 'all';
        $query = DB::table('campaign_links');
        if ($kind !== 'all') {
            $query->where('is_tagged', $kind === 'tagged');
        }
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function ($query) use ($pattern) {
                $query->whereRaw("LOWER(tags) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                    ->orWhereRaw("path LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereExists(function ($referrers) use ($pattern) {
                        $referrers->selectRaw('1')->from('campaign_link_referrers')->whereColumn('campaign_link_id', 'campaign_links.id')
                            ->whereRaw("host LIKE LOWER(?) ESCAPE '!'", [$pattern]);
                    });
            });
        }

        $totals = (clone $query)->selectRaw('COUNT(*) AS links, SUM(visits) AS visits, SUM(converted_visits) AS converted_visits, SUM(contacts) AS contacts, SUM(registrations) AS registrations')->first();
        $countries = $this->countries(clone $query);
        $countryCoverage = (int) ($totals->visits ?? 0) - $countries->sum(fn ($country) => $country->tagged_visits + $country->untagged_visits);
        $links = $query->orderByDesc(match ($sort) {
            'visits' => 'visits',
            'conversions' => 'converted_visits',
            default => 'last_seen_at',
        })->orderByDesc('id')->paginate(25)->withQueryString();
        $baseUrl = rtrim(SiteSettings::value('canonical_url'), '/');
        $referrers = $this->referrers($links->pluck('id')->all());
        $linkCountries = DB::table('campaign_link_countries')->whereIn('campaign_link_id', $links->pluck('id')->all())
            ->orderByDesc('visits')->orderBy('country_code')->get()->groupBy('campaign_link_id');
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

        return response()->view('admin.campaigns', compact('links', 'totals', 'search', 'sort', 'kind', 'countries', 'countryCoverage'))->header('Cache-Control', 'private, no-store');
    }

    private function countries(Builder $filteredLinks)
    {
        return DB::table('campaign_link_countries as locations')
            ->joinSub($filteredLinks->select('id', 'is_tagged'), 'links', 'links.id', '=', 'locations.campaign_link_id')
            ->select('locations.country_code')
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN locations.visits ELSE 0 END) AS tagged_visits')
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN locations.converted_visits ELSE 0 END) AS tagged_conversions')
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN 0 ELSE locations.visits END) AS untagged_visits')
            ->selectRaw('SUM(CASE WHEN links.is_tagged THEN 0 ELSE locations.converted_visits END) AS untagged_conversions')
            ->groupBy('locations.country_code')
            ->orderByRaw('SUM(locations.visits) DESC')
            ->orderBy('locations.country_code')
            ->get();
    }

    private function referrers(array $linkIds)
    {
        if ($linkIds === []) {
            return collect();
        }
        $sources = DB::table('campaign_link_referrers')->whereIn('campaign_link_id', $linkIds)
            ->select('campaign_link_id', 'host', 'visits')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY campaign_link_id ORDER BY visits DESC, host) AS source_rank')
            ->selectRaw('SUM(visits) OVER (PARTITION BY campaign_link_id) AS measured');

        return DB::query()->fromSub($sources, 'ranked_sources')->where('source_rank', '<=', 10)
            ->orderBy('source_rank')->get()->groupBy('campaign_link_id');
    }
}
