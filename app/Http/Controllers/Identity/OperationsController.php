<?php

namespace App\Http\Controllers\Identity;

use App\Dna\Impact;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Operations\Metrics;
use App\Operations\Reports;
use App\Operations\Scope;
use App\Operations\Window;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    private function context(Request $request, Organization $organization): array
    {
        $input = $request->validate([
            'group' => 'nullable|integer|min:1', 'property' => 'nullable|integer|min:1',
            'building' => 'nullable|integer|min:1', 'floor' => 'nullable|integer|min:1',
            'space' => 'nullable|integer|min:1', 'asset' => 'nullable|integer|min:1',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
        ]);
        $filters = array_filter($input, fn ($value) => $value !== null);
        $window = new Window($filters, $organization->timezone);
        $filters = array_replace($filters, $window->filters());

        return [new Scope($request->user('web'), $organization, $filters), $window, $filters];
    }

    public function index(Request $request, Organization $organization, Metrics $engine)
    {
        [$scope, $window, $filters] = $this->context($request, $organization);
        $queries = $engine->queries($scope, $window);
        $input = $request->validate(['metric' => ['nullable', Rule::in(array_keys($queries))], 'simulate' => 'nullable|boolean', 'view' => 'nullable|in:assets,spaces']);
        $counts = $engine->counts($queries);
        $metric = $input['metric'] ?? null;
        $records = $metric ? (clone $queries[$metric])->latest('id')->paginate(20, ['*'], 'items')->withQueryString() : null;
        $recent = isset($queries['completed']) ? (clone $queries['completed'])->latest('resolved_at')->latest('id')->limit(5)->get() : collect();
        $current = $engine->activity($scope, $window);
        $previous = $engine->activity($scope, $window, true);
        $cost = $engine->costs($scope, $window);
        $priorCost = $engine->costs($scope, $window, true);
        $preventive = isset($queries['preventive']) ? $engine->preventive($queries['preventive']) : null;
        $preference = DB::table('operations_preferences')->where(['organization_id' => $organization->id, 'user_id' => $scope->user->id])->first();
        $canManage = $scope->capabilities['maintenance'] && $scope->properties(true)->exists();
        $reports = app(Reports::class)->recent($scope)->limit(8)->get();
        $items = $this->explorer($scope, $input['view'] ?? null);
        $recordRows = $records?->getCollection() ?? collect();
        $sourceBuildings = $scope->buildings()->whereIn('id', $recordRows->pluck('building_id')->filter()->unique())->get()->keyBy('id');
        $editableItems = $scope->properties(true)->whereIn('properties.id', $recordRows->pluck('property_id')->filter()->unique())->pluck('properties.id')->all();
        $portfolios = ! $scope->portfolio && ! $scope->property && ! $scope->building && ! $scope->asset
            ? $organization->groups()->whereHas('properties', fn ($q) => $q->whereIn('properties.id', $scope->properties()->select('properties.id')))
                ->orderBy('name')->orderBy('id')->simplePaginate(12, ['*'], 'portfolios')->withQueryString()
            : null;
        $impact = $request->boolean('simulate') && $scope->asset && $scope->capabilities['dna']
            ? app(Impact::class)->run($scope->user, $scope->asset->property, 'asset:'.$scope->asset->uuid, 4, 80)
            : null;

        return view('operations.index', compact('organization', 'scope', 'window', 'filters', 'counts', 'metric', 'records', 'current', 'previous', 'cost', 'priorCost', 'preventive', 'preference', 'canManage', 'reports', 'items', 'portfolios', 'impact', 'recent', 'sourceBuildings', 'editableItems'));
    }

    private function explorer(Scope $scope, ?string $view): ?array
    {
        if ($scope->asset) {
            return null;
        }
        if ($view === 'assets' || $scope->space || ($scope->building && ! $scope->capabilities['rooms'])) {
            $type = 'asset';
            $query = $scope->capabilities['assets'] ? $scope->assets() : null;
        } elseif ($scope->floor || ($scope->building && $view === 'spaces')) {
            $type = 'space';
            $query = $scope->matchSpace($scope->building->spaces()->getQuery());
        } elseif ($scope->building) {
            $type = 'floor';
            $query = $scope->building->floors();
        } elseif ($scope->property) {
            $type = 'building';
            $query = $scope->buildings();
        } else {
            $type = 'property';
            $query = $scope->properties();
        }

        return $query ? [$type, $query->orderBy('name')->orderBy('id')->simplePaginate(18, ['*'], 'explorer')->withQueryString()] : null;
    }

    public function preferences(Request $request, Organization $organization)
    {
        [$scope] = $this->context($request, $organization);
        $data = $request->validate(['layout' => 'required|in:overview,explorer', 'weekly_digest' => 'nullable|boolean']);
        $weekly = $request->boolean('weekly_digest');
        abort_if($weekly && (! $scope->capabilities['maintenance'] || ! $scope->properties(true)->exists()), 403);
        DB::table('operations_preferences')->upsert([
            'organization_id' => $organization->id,
            'user_id' => $scope->user->id,
            'layout' => $data['layout'],
            'weekly_digest' => $weekly,
        ], ['organization_id', 'user_id'], ['layout', 'weekly_digest']);

        return back()->with('status', __('ops.saved'));
    }

    public function export(Request $request, Organization $organization, Reports $reports)
    {
        [$scope, , $filters] = $this->context($request, $organization);
        $type = $request->validate(['type' => ['required', Rule::in(Reports::TYPES)], 'use_dates' => 'nullable|boolean'])['type'];
        if (in_array($type, ['weekly', 'monthly'], true) && ! $request->boolean('use_dates')) {
            $today = CarbonImmutable::today($organization->timezone);
            $filters = array_replace($filters, ['from' => $today->subDays($type === 'weekly' ? 6 : 29)->toDateString(), 'to' => $today->toDateString()]);
            $scope = new Scope($scope->user, $organization, $filters);
        }
        $reports->request($scope, $type);

        return redirect()->route('app.operations.index', [$organization] + $filters)->with('status', __('ops.queued'));
    }

    public function download(Request $request, Organization $organization, string $report, Reports $reports)
    {
        return $reports->download($request->user('web'), $organization, $report);
    }
}
