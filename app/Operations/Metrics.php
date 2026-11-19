<?php

namespace App\Operations;

use App\Dna\Graph;
use App\Maintenance\Settings;
use App\Models\Building;
use App\Models\DnaRelation;
use App\Models\Evidence\Conflict;
use App\Models\Evidence\Version;
use App\Models\MaintenanceIssue;
use App\Models\MaintenancePlan;
use App\Models\Space;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Metrics
{
    public const CLOSED_WORK = ['resolved', 'verified', 'closed', 'cancelled'];

    private const CLOSED_ISSUE = ['draft', 'closed', 'cancelled'];

    public function queries(Scope $scope, Window $window): array
    {
        $queries = [];
        if ($scope->capabilities['maintenance']) {
            $issues = $scope->records(MaintenanceIssue::class)->whereNotIn('state', self::CLOSED_ISSUE);
            $working = $scope->records(WorkOrder::class)->whereNotIn('state', self::CLOSED_WORK);
            $unassigned = (clone $working)->whereNull('assigned_to')->where(fn ($q) => $q->whereNull('team')->orWhere('team', ''));
            $overdue = (clone $working)->where('due_at', '<', now());
            $queries = [
                'open' => $issues,
                'critical' => (clone $issues)->where('severity', 'critical'),
                'unassigned' => $unassigned,
                'priority' => (clone $unassigned)->whereIn('priority', ['high', 'critical']),
                'overdue' => $overdue,
                'critical_overdue' => (clone $overdue)->where('priority', 'critical'),
                'upcoming' => $scope->records(MaintenancePlan::class)->where('state', 'active')->whereBetween('next_at', [now(), now()->addDays(30)]),
                'completed' => $this->resolved($scope, $window),
                'preventive' => $scope->records(WorkOrder::class)->whereNotNull('plan_id')->where('state', '!=', 'cancelled')
                    ->whereBetween('scheduled_for', [$window->filters()['from'], $window->filters()['to']]),
            ];
            [$from, $to] = $window->range();
            $queries['reported'] = $scope->records(MaintenanceIssue::class)->where('state', '!=', 'draft')
                ->where('reported_at', '>=', $from)->where('reported_at', '<', $to);
            if ($scope->properties(true)->exists()) {
                $queries['cost_orders'] = $this->resolved($scope, $window)->whereIn('property_id', $scope->properties(true)->select('properties.id'));
            }
            $reopened = DB::table('asset_audits')->where('organization_id', $scope->organization->id)->where('entity_type', 'issue')
                ->where('action', 'maintenance.issue_state')->whereIn('before_data->state', ['closed', 'cancelled'])
                ->where('after_data->state', 'reported')->where('created_at', '>=', $window->range()[0])->where('created_at', '<', $window->range()[1])
                ->groupBy('entity_uuid')->select('entity_uuid')->havingRaw('COUNT(*) >= 2');
            $queries['reopened'] = (clone $issues)->whereIn('uuid', $reopened);
            if ($scope->capabilities['rooms']) {
                $queries['observed_spaces'] = Space::whereIn('id', (clone $issues)->whereNotNull('space_id')->select('space_id'))
                    ->whereIn('property_id', $scope->properties()->select('properties.id'));
            }
            if ($scope->capabilities['assets']) {
                $grouped = $this->resolved($scope, $window)->whereNotNull('asset_id')->select('asset_id')->groupBy('asset_id', 'category')
                    ->havingRaw('COUNT(*) >= ?', [app(Settings::class)->all()['repeat_threshold']]);
                $queries['repeated'] = $scope->assets()->whereIn('id', $grouped);
                $queries['shared'] = $scope->assets()->where('is_system', true)->whereIn('id', (clone $working)->whereNotNull('asset_id')
                    ->select('asset_id')->groupBy('asset_id')->havingRaw('COUNT(*) >= 2'));
            }
        }
        if ($scope->capabilities['assets']) {
            $queries['attention'] = $scope->assets()->where(fn ($q) => $q->where('status', 'out_of_service')
                ->when(isset($queries['open']), fn ($asset) => $asset->orWhereIn('id', (clone $queries['open'])->whereNotNull('asset_id')->select('asset_id'))));
            if ($scope->capabilities['evidence']) {
                $queries['warranty'] = $scope->assets()->whereNotExists(function ($query) use ($scope) {
                    $query->selectRaw('1')->from('asset_fact_applications as application')
                        ->join('asset_evidence_links as link', 'link.id', '=', 'application.link_id')
                        ->join('evidence_assertions as assertion', 'assertion.id', '=', 'link.assertion_id')
                        ->whereColumn('application.asset_id', 'assets.id')->where('application.field', 'warranty_end')
                        ->where('application.status', 'active')->whereNull('link.withdrawn_at')->where('assertion.status', 'confirmed')
                        ->whereColumn('assertion.revision', 'application.source_revision')
                        ->whereIn('assertion.building_id', Building::whereIn('property_id', $scope->properties()->select('properties.id'))->select('id'))
                        ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('evidence_conflicts as conflict')->where('conflict.status', 'open')
                            ->where(fn ($e) => $e->whereColumn('conflict.assertion_id', 'assertion.id')->orWhereColumn('conflict.existing_assertion_id', 'assertion.id')));
                });
            }
        }
        if ($scope->capabilities['evidence']) {
            $queries['conflicts'] = Conflict::where('status', 'open')->whereIn('building_id', $scope->buildings()->select('id'));
            $queries['documents'] = Version::whereIn('building_id', $scope->buildings()->select('id'))
                ->whereHas('document', fn ($q) => $q->whereNull('archived_at'));
        }
        if ($scope->capabilities['dna']) {
            $queries['disputed'] = DnaRelation::where('organization_id', $scope->organization->id)->where('state', 'disputed')
                ->whereIn('property_id', $scope->properties()->select('properties.id'))
                ->when($scope->building || $scope->asset, function ($q) use ($scope) {
                    $key = fn ($kind) => app(Graph::class)->cat("'$kind:'", 'uuid');
                    $nodes = $scope->assets()->selectRaw($key('asset'));
                    $q->where(function ($matches) use ($scope, $nodes, $key) {
                        $matches->whereIn('source', clone $nodes)->orWhereIn('target', clone $nodes);
                        if (! $scope->asset) {
                            $spaces = $scope->matchSpace(Space::query())->selectRaw($key('space'));
                            $matches->orWhereIn('source', clone $spaces)->orWhereIn('target', clone $spaces);
                        }
                    });
                });
        }

        return $queries;
    }

    public function resolved(Scope $scope, Window $window, bool $previous = false): Builder
    {
        [$from, $to] = $window->range($previous);

        return $scope->records(WorkOrder::class)->whereIn('state', ['resolved', 'verified', 'closed'])
            ->where('resolved_at', '>=', $from)->where('resolved_at', '<', $to);
    }

    public function activity(Scope $scope, Window $window, bool $previous = false): ?array
    {
        if (! $scope->capabilities['maintenance']) {
            return null;
        }
        [$from, $to] = $window->range($previous);

        return [
            'reported' => $scope->records(MaintenanceIssue::class)->where('state', '!=', 'draft')
                ->where('reported_at', '>=', $from)->where('reported_at', '<', $to)->count(),
            'completed' => $this->resolved($scope, $window, $previous)->count(),
        ];
    }

    public function costs(Scope $scope, Window $window, bool $previous = false): array
    {
        if (! $scope->capabilities['maintenance']) {
            return ['rows' => collect(), 'incomplete' => null, 'available' => false];
        }
        $editable = $scope->properties(true);
        if (! (clone $editable)->exists()) {
            return ['rows' => collect(), 'incomplete' => null, 'available' => false];
        }
        $orders = $this->resolved($scope, $window, $previous)->whereIn('property_id', $editable->select('properties.id'));
        $incomplete = (clone $orders)->where(function ($q) {
            $q->whereNull('currency')->orWhereNull('labor_cost')->orWhereNull('external_cost')->orWhereNull('materials_cost');
        })->count();
        $rows = $orders->whereNotNull('currency')->select('currency')->selectRaw('SUM(labor_cost) AS labor, SUM(external_cost) AS external, SUM(materials_cost) AS materials')
            ->groupBy('currency')->orderBy('currency')->get();

        return ['rows' => $rows, 'incomplete' => $incomplete, 'available' => true];
    }

    public function preventive(Builder $plans): array
    {
        return [
            'scheduled' => (clone $plans)->count(),
            'on_time' => (clone $plans)->whereIn('state', ['resolved', 'verified', 'closed'])->whereNotNull('resolved_at')->whereColumn('resolved_at', '<=', 'due_at')->count(),
            'late' => (clone $plans)->whereIn('state', ['resolved', 'verified', 'closed'])->whereNotNull('resolved_at')->whereColumn('resolved_at', '>', 'due_at')->count(),
        ];
    }

    public function counts(array $queries): array
    {
        return array_map(fn ($query) => (clone $query)->count(), $queries);
    }
}
