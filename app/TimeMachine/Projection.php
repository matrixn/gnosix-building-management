<?php

namespace App\TimeMachine;

use App\Models\Building;
use App\Models\User;
use App\Policies\MaintenancePolicy;
use App\Support\CapabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Projection
{
    public const LIMIT = 2500;

    public function available(User $user, Building $building): array
    {
        $capabilities = app(CapabilityService::class);
        $allows = fn (string $key) => $capabilities->allows($user, $building->property->organization, $key);

        return [
            'structure' => $allows('hospitality.rooms'),
            'assets' => $allows('assets.view'),
            'relations' => $allows('assets.view') && $allows('hospitality.rooms') && $allows('dna.view'),
            'maintenance' => ! $building->trashed() && app(MaintenancePolicy::class)->report($user, $building->property),
            'evidence' => ! $building->trashed() && ($allows('evidence.upload') || $allows('evidence.review')),
        ];
    }

    public function reconstruct(User $user, Building $building, string $valid, CarbonImmutable $knowledge): array
    {
        $end = CarbonImmutable::parse($valid, $building->property->timezone)->endOfDay()->utc();
        $observed = min($end, $knowledge)->format('Y-m-d H:i:s');
        $known = $knowledge->format('Y-m-d H:i:s');
        $access = $this->available($user, $building);
        $sections = $this->structure($building, $observed, $access['structure']);
        $partial = $sections['partial'];
        $rows = $sections['rows'];
        $assets = [];
        if ($access['assets']) {
            $assets = $this->assets($building, $observed, $known, $valid, $access['structure']);
            $partial = $partial || $assets['partial'];
            $rows += $assets['rows'];
        }
        if ($access['relations']) {
            $relations = $this->relations($building, $observed, $known, $rows, $access['evidence']);
            $partial = $partial || $relations['partial'];
            $rows += $relations['rows'];
        }
        if ($access['maintenance']) {
            $maintenance = $this->maintenance($user, $building, $observed, $access);
            $partial = $partial || $maintenance['partial'];
            $rows += $maintenance['rows'];
        }
        if ($access['evidence']) {
            $sources = app(Sources::class)->read($building, $valid, $known, $access);
            $partial = $partial || $sources['partial'];
            $rows += $sources['rows'];
        }
        if (isset($rows['building:'.$building->uuid]) && in_array($rows['building:'.$building->uuid]['state'], ['archived', 'deleted'], true)) {
            $rows = array_filter($rows, fn ($row) => ! in_array($row['kind'], ['floor', 'space', 'unit', 'asset', 'system', 'relation']));
        }
        $dateEnd = $end->format('Y-m-d H:i:s');
        foreach ($rows as &$row) {
            $row['later'] = max($row['recorded'], $row['uploaded'] ?? '') > $dateEnd;
        }
        unset($row);
        uasort($rows, fn ($a, $b) => [$a['kind'], $a['label'], $a['key']] <=> [$b['kind'], $b['label'], $b['key']]);

        return ['valid' => $valid, 'knowledge' => $knowledge->toIso8601String(), 'observed' => $observed, 'rows' => $rows, 'partial' => $partial, 'access' => $access];
    }

    public static function row(string $key, string $kind, string $label, string $state, array $fields, string $recorded, string $source, array $extra = []): array
    {
        return $extra + compact('key', 'kind', 'label', 'state', 'fields', 'recorded', 'source') + ['effective' => null, 'url' => null, 'reviewed' => null, 'uploaded' => null, 'support' => 'declared'];
    }

    public static function json(?string $value): array
    {
        return $value === null ? [] : (json_decode($value, true) ?? []);
    }

    public static function latest(Builder $query, string $partition, string $order): Builder
    {
        return DB::query()->fromSub($query->selectRaw("ROW_NUMBER() OVER (PARTITION BY $partition ORDER BY $order) AS temporal_rank"), 'temporal_rows')
            ->where('temporal_rank', 1);
    }

    private function structure(Building $b, string $cutoff, bool $spatial): array
    {
        $base = DB::table('building_revisions')->where('building_id', $b->id)->where('created_at', '<=', $cutoff);
        $checkpoint = (clone $base)->where('entity_type', 'declaration')->latest('id')->first();
        $rows = [];
        $floorIds = $spatial ? DB::table('floors')->where('building_id', $b->id)->pluck('uuid', 'id') : collect();
        $direct = (clone $base)->whereIn('entity_type', $spatial ? ['building', 'floor', 'space'] : ['building'])
            ->when($checkpoint, fn ($q) => $q->where('id', '>', $checkpoint->id))->select('building_revisions.*');
        $events = self::latest($direct, 'entity_type, entity_reference', 'id DESC')->orderBy('id')->limit(self::LIMIT + 1)->get();
        if ($checkpoint) {
            $snapshot = self::json($checkpoint->after_data);
            $identity = $snapshot['identity'] ?? [];
            $this->structureRow($rows, 'building', $b->uuid, $identity, $checkpoint, $checkpoint->action === 'deleted');
            if ($spatial) {
                foreach ($snapshot['levels'] ?? [] as $position => $floor) {
                    $this->structureRow($rows, 'floor', $floor['key'], $floor + ['position' => $position], $checkpoint);
                    foreach ($floor['spaces'] as $space) {
                        $this->structureRow($rows, 'space', $space['key'], $space + ['floor' => $floor['key']], $checkpoint);
                    }
                }
                foreach ($snapshot['unplaced_spaces'] ?? [] as $space) {
                    $this->structureRow($rows, 'space', $space['key'], $space, $checkpoint);
                }
                foreach ($snapshot['units'] ?? [] as $unit) {
                    $this->structureRow($rows, 'unit', $unit['key'], $unit, $checkpoint);
                }
            }
        }
        foreach ($events->take(self::LIMIT) as $event) {
            $data = self::json($event->after_data);
            if ($event->entity_type === 'space') {
                $data['floor'] = $floorIds[$data['floor_id'] ?? 0] ?? null;
            }
            $removed = ! empty($data['archived_at']) || ($event->entity_type === 'space' && (int) ($data['building_id'] ?? 0) !== $b->id);
            $this->structureRow($rows, $event->entity_type, $event->entity_reference, $data, $event, $removed);
        }

        return ['rows' => $rows, 'partial' => $events->count() > self::LIMIT || count($rows) > self::LIMIT];
    }

    private function structureRow(array &$rows, string $kind, string $id, array $data, object $event, bool $removed = false): void
    {
        $fields = match ($kind) {
            'building' => ['reference', 'classification', 'address', 'construction_year'],
            'floor' => ['position', 'kind'],
            'space' => ['floor', 'space_type', 'number', 'bed_count', 'capacity', 'area_m2'],
            'unit' => ['unit_type', 'space_keys'],
        };
        $key = "$kind:$id";
        $rows[$key] = self::row($key, $kind, ($data['display_name'] ?? null) ?: ($data['name'] ?? $id), $removed ? ($kind === 'building' ? 'deleted' : 'archived') : ($data['state'] ?? 'recorded'), Arr::only($data, $fields), $event->created_at, 'Building Studio #'.$event->id);
    }

    private function assets(Building $b, string $cutoff, string $known, string $valid, bool $spatial): array
    {
        $query = DB::table('asset_audits as history')->join('assets as asset', 'asset.id', '=', 'history.asset_id')
            ->where('asset.property_id', $b->property_id)->where('asset.organization_id', $b->property->organization_id)
            ->where('history.organization_id', $b->property->organization_id)
            ->whereIn('history.action', ['created', 'updated', 'archived', 'restored', 'building_purged'])
            ->select('history.*', 'asset.uuid as asset_uuid');
        // Rank before filtering location so a move away cannot resurrect an older placement.
        $observations = (clone $query)->where('history.created_at', '<=', $cutoff);
        $events = self::latest($observations, 'history.asset_id', 'history.id DESC')
            ->where('after_data->building_id', $b->id)->orderBy('asset_id')->limit(self::LIMIT + 1)->get();
        $rows = [];
        $floorIds = $spatial ? DB::table('floors')->where('building_id', $b->id)->pluck('uuid', 'id') : collect();
        $spaceIds = $spatial ? DB::table('spaces')->where('building_id', $b->id)->pluck('uuid', 'id') : collect();
        $served = self::latest((clone $observations)->whereIn('history.action', ['created', 'updated']), 'history.asset_id', 'history.id DESC')
            ->whereIntegerInRaw('asset_id', $events->take(self::LIMIT)->pluck('asset_id'))->get()->keyBy('asset_id');
        foreach ($events->take(self::LIMIT) as $event) {
            $data = self::json($event->after_data);
            // Retention can detach an asset. Never republish its old placement if a containing building was purged.
            $manual = $data['manual_data'] ?? [];
            $installed = self::date($manual['installation_date'] ?? null);
            $key = 'asset:'.$event->asset_uuid;
            $fields = Arr::only($data, ['reference', 'manufacturer', 'model', 'status']);
            $fields += Arr::only($manual, ['manufacturer', 'model', 'installation_date', 'warranty_start', 'warranty_end']);
            if ($spatial) {
                $fields['floor'] = $floorIds[$data['floor_id'] ?? 0] ?? null;
                $fields['space'] = $spaceIds[$data['space_id'] ?? 0] ?? null;
                $placement = self::json($served->get($event->asset_id)?->after_data);
                $fields['served_spaces'] = array_values(array_filter(array_map(fn ($id) => $spaceIds[$id] ?? null, $placement['served_spaces'] ?? [])));
            }
            $rows[$key] = self::row($key, empty($data['is_system']) ? 'asset' : 'system', $data['name'] ?? $event->asset_uuid, ! empty($data['archived_at']) ? 'archived' : ($installed && $installed > $valid ? 'not_yet_installed' : 'recorded'), $fields, $event->created_at, 'Asset Passport #'.$event->id, ['effective' => $installed, 'system_id' => $data['system_id'] ?? null, 'asset_id' => $event->asset_id]);
        }
        $systems = array_column($rows, 'key', 'asset_id');
        foreach ($rows as &$row) {
            $row['fields']['system'] = $systems[$row['system_id'] ?? 0] ?? null;
            unset($row['system_id'], $row['asset_id']);
        }
        unset($row);

        // A later registration can document an earlier installation, but does not backdate placement or status.
        $installations = $known > $cutoff ? (clone $query)->where('history.action', 'created')
            ->where('history.created_at', '>', $cutoff)->where('history.created_at', '<=', $known)
            ->where('history.after_data->building_id', $b->id)
            ->where('history.after_data->manual_data->installation_date', '<=', $valid)
            ->orderBy('history.id')->limit(self::LIMIT + 1)->get() : collect();
        foreach ($installations->take(self::LIMIT) as $event) {
            $data = self::json($event->after_data);
            $effective = self::date($data['manual_data']['installation_date'] ?? null);
            if ($effective === null) {
                continue;
            }
            $key = 'installation:'.$event->asset_uuid;
            $rows[$key] = self::row($key, 'installation', $data['name'] ?? $event->asset_uuid, 'recorded',
                ['reference' => $data['reference'] ?? null], $event->created_at, 'Asset Passport #'.$event->id, ['effective' => $effective]);
        }

        return ['rows' => $rows, 'partial' => $events->count() > self::LIMIT || $installations->count() > self::LIMIT];
    }

    private function relations(Building $b, string $cutoff, string $known, array $nodes, bool $evidence): array
    {
        $keys = array_keys(array_filter($nodes, fn ($row) => ! in_array($row['state'], ['archived', 'deleted', 'not_yet_installed'], true)));
        $allKeys = array_fill_keys($keys, true);
        $boundedKeys = array_slice($keys, 0, self::LIMIT);
        $edges = DB::table('dna_relations')->where('property_id', $b->property_id)->where('organization_id', $b->property->organization_id);
        // Keep parameter counts portable for large SQLite installations.
        $edges->where(function ($query) use ($boundedKeys) {
            foreach (array_chunk($boundedKeys, 350) as $chunk) {
                $query->orWhereIn('source', $chunk)->orWhereIn('target', $chunk);
            }
        });
        if ($keys === []) {
            return ['rows' => [], 'partial' => false];
        }
        $events = self::latest(
            DB::table('dna_relation_revisions as history')->joinSub($edges->select('id'), 'relation', 'relation.id', '=', 'history.dna_relation_id')
                ->where('history.created_at', '<=', $cutoff)->select('history.*'),
            'history.dna_relation_id',
            'history.revision DESC',
        )->orderBy('dna_relation_id')->limit(self::LIMIT + 1)->get();
        $revisions = $events->take(self::LIMIT)->map(fn ($event) => [$event, self::json($event->snapshot)]);
        $sourceIds = $evidence ? $revisions->flatMap(fn ($item) => array_column($item[1]['sources'] ?? [], 'version_id'))->filter()->unique()->take(self::LIMIT) : collect();
        $versions = DB::table('evidence_versions')->where('building_id', $b->id)->where('created_at', '<=', $known)
            ->whereIn('id', $sourceIds)->get(['id', 'original_name', 'number', 'created_at'])->keyBy('id');
        $rows = [];
        $managed = [];
        foreach ($revisions as [$event, $data]) {
            $key = 'relation:'.$data['uuid'];
            $state = $data['state'];
            if ($data['backing'] === 'adapter' && $data['type_key'] === 'serves') {
                $managed[$data['source'].'|'.$data['target']] = true;
                $asset = $nodes[$data['source']] ?? null;
                $expected = in_array(substr($data['target'], 6), $asset['fields']['served_spaces'] ?? [], true);
                if ($asset && $asset['recorded'] > $event->created_at && $state !== 'superseded' && ! $expected) {
                    $state = 'unknown';
                }
            }
            if (in_array($state, ['declared', 'confirmed'], true) && (! isset($allKeys[$data['source']]) || ! isset($allKeys[$data['target']]))) {
                $state = 'unknown';
            }
            $fields = ['source' => $nodes[$data['source']]['label'] ?? null, 'target' => $nodes[$data['target']]['label'] ?? null];
            $extra = ['reviewed' => $data['reviewed_at'] ?? null, 'support' => $state === 'confirmed' ? 'human_reviewed' : 'declared'];
            foreach ($data['sources'] ?? [] as $source) {
                $version = $versions->get($source['version_id'] ?? '');
                if (! $version) {
                    continue;
                }
                $page = $source['page'] ?? null;
                $fields['evidence_sources'][] = $version->original_name.' · R'.$version->number.($page ? ' · p. '.$page : '');
                $extra['url'] ??= route('app.evidence.show', [$b->property->organization, $b->property, $b->uuid, 'version' => $version->id, 'page' => $page]);
                $extra['uploaded'] ??= $version->created_at;
            }
            $rows[$key] = self::row($key, 'relation', $data['type_key'], $state, $fields, $event->created_at, 'Building DNA #'.$event->id, $extra);
        }

        $nativeCount = 0;
        foreach ($nodes as $node) {
            if (! isset($allKeys[$node['key']]) || ! in_array($node['kind'], ['asset', 'system'], true)) {
                continue;
            }
            foreach ($node['fields']['served_spaces'] ?? [] as $space) {
                $target = 'space:'.$space;
                if (! isset($allKeys[$target]) || isset($managed[$node['key'].'|'.$target])) {
                    continue;
                }
                if (++$nativeCount > self::LIMIT) {
                    break 2;
                }
                $key = 'serves:'.$node['key'].':'.$space;
                $rows[$key] = self::row($key, 'relation', 'serves', 'declared', ['source' => $node['label'], 'target' => $nodes[$target]['label']], $node['recorded'], $node['source']);
            }
        }

        return ['rows' => $rows, 'partial' => $events->count() > self::LIMIT || count($keys) > self::LIMIT || $nativeCount > self::LIMIT];
    }

    private function maintenance(User $u, Building $b, string $cutoff, array $access): array
    {
        $drafts = app(MaintenancePolicy::class)->manage($u, $b->property);
        $base = DB::table('asset_audits')->where('organization_id', $b->property->organization_id)->where('property_id', $b->property_id)
            ->where('created_at', '<=', $cutoff)->whereIn('entity_type', ['work', 'issue'])->select('asset_audits.*');
        $events = self::latest($base, 'entity_type, entity_uuid', 'id DESC')->where('after_data->building_id', $b->id)
            ->orderBy('entity_uuid')->limit(self::LIMIT + 1)->get();
        $rows = [];
        foreach ($events->take(self::LIMIT) as $event) {
            $data = self::json($event->after_data);
            if ($event->entity_type === 'issue' && ($data['state'] ?? '') === 'draft' && ! $drafts && (int) ($data['created_by'] ?? 0) !== $u->id) {
                continue;
            }
            $key = $event->entity_type.':'.$event->entity_uuid;
            $rows[$key] = self::row($key, $event->entity_type, $data['title'] ?? $data['reference'], $data['state'], Arr::only($data, ['reference', 'category', 'priority', 'resolved_at', 'closed_at']), $event->created_at, 'Maintenance #'.$event->id);
        }
        $interventions = (clone $base)->where('after_data->building_id', $b->id)->where('action', 'maintenance.performed')
            ->orderByDesc('id')->limit(self::LIMIT + 1)->get();
        foreach ($interventions->take(self::LIMIT) as $event) {
            $data = self::json($event->after_data);
            $key = 'intervention:'.$event->id;
            $rows[$key] = self::row($key, 'intervention', $data['title'] ?? $data['reference'], 'recorded', Arr::only($data, ['reference', 'work_performed']), $event->created_at, 'Maintenance #'.$event->id);
        }
        $reports = app(Sources::class)->reports($u, $b, $cutoff, $access['evidence'], $drafts);

        return ['rows' => $rows + $reports['rows'], 'partial' => $events->count() > self::LIMIT || $interventions->count() > self::LIMIT || $reports['partial']];
    }

    public static function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    public function compare(array $before, array $after): array
    {
        $changes = [];
        $complete = ! $before['partial'] && ! $after['partial'];
        foreach (array_unique([...array_keys($before['rows']), ...array_keys($after['rows'])]) as $key) {
            $a = $before['rows'][$key] ?? null;
            $b = $after['rows'][$key] ?? null;
            if ((! $a || ! $b) && ! $complete) {
                continue;
            }
            $meaning = fn ($row) => $row ? Arr::only($row, ['label', 'state', 'fields', 'effective', 'reviewed', 'support', 'later', 'future_fact']) : null;
            if ($meaning($a) === $meaning($b)) {
                continue;
            }
            $changes[$key] = ['change' => $a && $b ? 'changed' : ($b ? 'added' : 'removed'), 'before' => $a, 'after' => $b, 'kind' => ($b ?? $a)['kind']];
        }

        return $changes;
    }
}
