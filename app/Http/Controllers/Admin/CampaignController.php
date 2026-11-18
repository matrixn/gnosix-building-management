<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = $request->validate(['q' => 'nullable|string|max:120', 'sort' => 'nullable|in:recent,visits,conversions']);
        $search = $input['q'] ?? '';
        $sort = $input['sort'] ?? 'recent';
        $query = DB::table('campaign_links');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->whereRaw("LOWER(tags) LIKE LOWER(?) ESCAPE '!'", [$pattern]);
        }

        $totals = (clone $query)->selectRaw('COUNT(*) AS links, SUM(visits) AS visits, SUM(converted_visits) AS converted_visits, SUM(contacts) AS contacts, SUM(registrations) AS registrations')->first();
        $links = $query->orderByDesc(match ($sort) {
            'visits' => 'visits',
            'conversions' => 'converted_visits',
            default => 'last_seen_at',
        })->orderByDesc('id')->paginate(25)->withQueryString();
        $baseUrl = rtrim(SiteSettings::value('canonical_url'), '/');
        $links->through(function ($link) use ($baseUrl) {
            $link->tags = json_decode($link->tags, true, flags: JSON_THROW_ON_ERROR);
            $link->url = $baseUrl.$link->path.'?'.http_build_query($link->tags, encoding_type: PHP_QUERY_RFC3986);

            return $link;
        });

        return response()->view('admin.campaigns', compact('links', 'totals', 'search', 'sort'))->header('Cache-Control', 'private, no-store');
    }
}
