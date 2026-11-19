<?php

namespace App\Trust;

use App\Dna\Graph;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Conflict;
use App\Models\Evidence\Version;
use App\Models\MaintenanceIssue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\ServiceReport;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\CapabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class Passport
{
    public const CHECKS = ['identity', 'inventory', 'outstanding', 'disclosure'];

    public const SECTIONS = ['assets', 'maintenance', 'evidence', 'dna', 'history'];

    public const SAMPLE = 40;

    public function authorize(User $user, Organization $org, Property $property, Building $building, bool $write = false): array
    {
        abort_unless((int) $property->organization_id === $org->id && (int) $building->property_id === $property->id && ! $building->trashed(), 404);
        abort_if($user->isArchived() || ! $user->hasVerifiedEmail() || $building->state === 'archived', 403);
        Gate::forUser($user)->authorize($write ? 'update' : 'view', $property);
        $capabilities = app(CapabilityService::class)->forOrganization($user, $org);
        $can = fn ($key) => $capabilities[$key]['allowed'] ?? false;
        abort_unless($can('trust.view') && (! $write || $can('trust.manage')), 403);

        return [
            'assets' => $can('assets.view'), 'maintenance' => $can('hospitality.maintenance'),
            'evidence' => $can('evidence.upload') || $can('evidence.review'),
            'dna' => $can('dna.view') && $can('hospitality.rooms') && $can('assets.view'),
            'history' => $can('time.view'),
        ];
    }

    public function documents(Building $building): Builder
    {
        return Version::where('building_id', $building->id)->whereHas('document', fn ($query) => $query
            ->where('building_id', $building->id)->whereNull('archived_at'));
    }

    public function build(Building $building, Organization $org, array $permissions, ?array $selection = null): array
    {
        $shared = $selection !== null;
        $data = [
            'schema' => 'gnosix.trust-passport.v1',
            'reference' => 'TP-'.$building->uuid,
            'captured_at' => now()->toIso8601String(),
            'building' => $building->only(['uuid', 'reference', 'name', 'classification', 'construction_year', 'country', 'state']),
            'source_organization' => $org->name,
            'coverage' => [], 'findings' => [], 'sections' => [], 'independent_verification' => 'not_assessed',
            'sample_limit' => self::SAMPLE,
        ];
        $present = fn ($section) => ($permissions[$section] ?? false) && (! $shared || in_array($section, $selection['sections'], true));
        $sample = fn ($query) => (clone $query)->orderByDesc('id')->limit(self::SAMPLE)->get();
        if ($present('evidence')) {
            $documents = $this->documents($building);
            if ($shared) {
                $documents->whereIn('id', $selection['versions']);
            }
            $types = ['technical', 'installation', 'inspection', 'warranty', 'maintenance'];
            $covered = DB::table('evidence_documents')->whereIn('id', (clone $documents)->select('document_id'))->distinct()->pluck('document_type')->all();
            $data['coverage']['documentation'] = [count(array_intersect($types, $covered)), count($types)];
            $documentRows = $sample($documents);
            $unavailable = $documentRows->reject(fn ($row) => Storage::disk('evidence')->exists($row->path));
            $data['sections']['documents'] = [
                'total' => (clone $documents)->count(),
                'items' => $documentRows->map->only(['id', 'number', 'original_name', 'classification', 'sha256', 'created_at'])->all(),
            ];
            if ($unavailable->isNotEmpty()) {
                $data['findings'][] = ['code' => 'unavailable', 'count' => $unavailable->count(), 'section' => 'evidence', 'record' => $shared ? null : $unavailable->first()->id];
            }
            if (! in_array('technical', $covered, true)) {
                $data['findings'][] = ['code' => 'missing_technical', 'count' => 1, 'section' => 'evidence'];
            }

            // Assertions can refer to sensitive details. Shared packets expose counts only for explicitly offered versions.
            $facts = Assertion::where('building_id', $building->id)->when($shared, fn ($q) => $q->whereIn('version_id', $selection['versions']));
            $status = (clone $facts)->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
            $documented = (clone $facts)->whereNotNull('version_id')->count();
            $total = array_sum($status);
            $openConflicts = Conflict::where('building_id', $building->id)->where('status', 'open')
                ->where(fn ($q) => $q->whereIn('assertion_id', (clone $facts)->select('id'))->orWhereIn('existing_assertion_id', (clone $facts)->select('id')));
            $conflictCount = (clone $openConflicts)->count();
            $conflicted = (clone $facts)->where(fn ($q) => $q->where('status', 'disputed')
                ->orWhereIn('id', (clone $openConflicts)->select('assertion_id'))->orWhereIn('id', (clone $openConflicts)->select('existing_assertion_id')))->count();
            $data['coverage']['consistency'] = [$total - $conflicted, $total];
            $data['sections']['evidence'] = ['status' => $status, 'documented' => $documented, 'conflicts' => $conflictCount];
            if ($conflictCount || $conflicted) {
                $fact = $shared ? null : ((clone $openConflicts)->value('assertion_id') ?? (clone $facts)->where('status', 'disputed')->value('id'));
                $data['findings'][] = ['code' => 'conflict', 'count' => max($conflictCount, $conflicted), 'section' => 'evidence', 'record' => $fact];
            }
            $serial = (clone $facts)->where('semantic_key', 'equipment.serial_number')->where(fn ($q) => $q->where('status', 'disputed')
                ->orWhereIn('id', (clone $openConflicts)->select('assertion_id'))->orWhereIn('id', (clone $openConflicts)->select('existing_assertion_id')));
            if ($count = (clone $serial)->count()) {
                $data['findings'][] = ['code' => 'serial_conflict', 'count' => $count, 'section' => 'evidence', 'record' => $shared ? null : $serial->value('id')];
            }
        }
        if ($present('assets')) {
            $assets = Asset::where('organization_id', $org->id)->where('property_id', $building->property_id)
                ->whereNull('archived_at')->where('building_id', $building->id);
            $total = (clone $assets)->count();
            $identified = (clone $assets)->whereNotNull('serial_key')->where('serial_key', '!=', '')->count();
            $warranty = (clone $assets)->whereNotNull('manual_data->warranty_end')->count();
            $data['coverage']['identification'] = [$identified, $total];
            $data['coverage']['warranty'] = [$warranty, $total];
            $data['sections']['assets'] = [
                'total' => $total,
                'items' => $sample($assets)->map(fn ($asset) => $asset->only(['uuid', 'reference', 'name', 'status', 'is_system']) + [
                    'warranty_end' => $asset->manual_data['warranty_end'] ?? null,
                    'declared_identity' => filled($asset->serial_key),
                ])->all(),
            ];
            if (! $shared && $present('evidence') && $warranty) {
                $unverified = (clone $assets)->whereNotNull('manual_data->warranty_end')->whereNotExists(function ($q) use ($building) {
                    $q->selectRaw('1')->from('asset_fact_applications as approved')
                        ->join('asset_evidence_links as link', 'link.id', '=', 'approved.link_id')
                        ->join('evidence_assertions as fact', 'fact.id', '=', 'link.assertion_id')
                        ->whereColumn('approved.asset_id', 'assets.id')->whereColumn('link.asset_id', 'assets.id')
                        ->whereNull('link.withdrawn_at')->where('approved.field', 'warranty_end')->where('approved.status', 'active')
                        ->where('fact.building_id', $building->id)->where('fact.status', 'confirmed')->whereColumn('fact.revision', 'approved.source_revision')
                        ->whereIn('fact.version_id', $this->documents($building)->select('id'))
                        ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('evidence_conflicts as conflict')->where('conflict.status', 'open')
                            ->where(fn ($q) => $q->whereColumn('conflict.assertion_id', 'fact.id')->orWhereColumn('conflict.existing_assertion_id', 'fact.id')));
                });
                if ($count = (clone $unverified)->count()) {
                    $data['findings'][] = ['code' => 'warranty_unverified', 'count' => $count, 'section' => 'assets', 'record' => $unverified->value('uuid')];
                }
            }
            if ($total > $warranty) {
                $asset = $shared ? null : (clone $assets)->whereNull('manual_data->warranty_end')->value('uuid');
                $data['findings'][] = ['code' => 'warranty_unknown', 'count' => $total - $warranty, 'section' => 'assets', 'record' => $asset];
            }
        }
        if ($present('maintenance')) {
            $works = WorkOrder::where('organization_id', $org->id)->where('property_id', $building->property_id)->where('building_id', $building->id);
            $issues = MaintenanceIssue::where('organization_id', $org->id)->where('property_id', $building->property_id)->where('building_id', $building->id)
                ->whereNotIn('state', ['draft', 'closed', 'cancelled']);
            $open = (clone $works)->whereNotIn('state', ['resolved', 'verified', 'closed', 'cancelled']);
            $closed = (clone $works)->whereIn('state', ['resolved', 'verified', 'closed'])->whereNotNull('resolved_at');
            $recorded = (clone $closed)->whereNotNull('work_performed')->where('work_performed', '!=', '')->count();
            $data['coverage']['maintenance'] = [$recorded, (clone $closed)->count()];
            $data['sections']['maintenance'] = [
                'open_orders' => (clone $open)->count(), 'open_issues' => (clone $issues)->count(), 'resolved' => (clone $closed)->count(),
                'total' => (clone $works)->count(),
                'issues' => $sample($issues)->map(fn ($issue) => $issue->only(['uuid', 'reference', 'state', 'category', 'severity']) + ($shared ? [] : ['title' => $issue->title]))->all(),
                'items' => $sample($works)->map(fn ($row) => $row->only(['uuid', 'reference', 'state', 'category', 'priority', 'resolved_at']) + ($shared ? [] : ['title' => $row->title]))->all(),
            ];
            $critical = (clone $open)->where('priority', 'critical');
            if ($count = (clone $critical)->count()) {
                $data['findings'][] = ['code' => 'critical_work', 'count' => $count, 'section' => 'maintenance', 'record' => $shared ? null : $critical->value('uuid')];
            }
            $pending = ServiceReport::whereIn('work_order_id', (clone $works)->select('id'))->where('state', 'submitted')->whereDoesntHave('reviews');
            if ($count = $pending->count()) {
                $data['findings'][] = ['code' => 'pending_review', 'count' => $count, 'section' => 'maintenance', 'record' => $shared ? null : (clone $works)->whereIn('id', $pending->select('work_order_id'))->value('uuid')];
            }
        }
        if ($present('dna')) {
            $graph = app(Graph::class);
            $nodes = $graph->nodes($building->property)->where('building_key', 'building:'.$building->uuid)->select('node_key');
            $relations = DB::query()->fromSub($graph->edges($building->property), 'trust_edges')
                ->where('state', '!=', 'superseded')->where(fn ($q) => $q->whereIn('source', clone $nodes)->orWhereIn('target', clone $nodes));
            $data['sections']['dna'] = [
                'total' => (clone $relations)->count(),
                'items' => (clone $relations)->orderBy('edge_key')->limit(self::SAMPLE)->get()
                    ->map(fn ($row) => array_intersect_key((array) $row, array_flip($shared ? ['type_key', 'state'] : ['edge_key', 'type_key', 'state', 'source', 'target'])))->all(),
            ];
            $systems = Asset::where('building_id', $building->id)->where('property_id', $building->property_id)->where('organization_id', $org->id)
                ->whereNull('archived_at')->where('is_system', true)
                ->whereNull('system_id')->whereDoesntHave('components')->whereDoesntHave('servedSpaces')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('dna_relations')
                ->where('property_id', $building->property_id)->whereIn('state', ['declared', 'confirmed'])
                ->where(fn ($q) => $q->where('source', DB::raw($graph->cat("'asset:'", 'assets.uuid')))->orWhere('target', DB::raw($graph->cat("'asset:'", 'assets.uuid')))));
            if ($count = (clone $systems)->count()) {
                $data['findings'][] = ['code' => 'isolated_system', 'count' => $count, 'section' => 'assets', 'record' => $shared ? null : $systems->value('uuid')];
            }
        }
        if ($present('history')) {
            $history = $building->revisions();
            $data['sections']['history'] = [
                'total' => (clone $history)->count(),
                'items' => $history->latest('id')->limit(self::SAMPLE)->get(['action', 'entity_type', 'source_kind', 'created_at'])->toArray(),
            ];
        }

        return $data;
    }
}
