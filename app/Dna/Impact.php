<?php

namespace App\Dna;

use App\Models\Property;
use App\Models\User;

/** Read-only potential reachability. It never changes operational status or diagnoses a defect. */
class Impact
{
    public function run(User $u, Property $p, string $start, ?int $depth = null, ?int $nodeLimit = null, bool $validate = false): array
    {
        app(Authorization::class)->check($u, $p);
        $g = app(Graph::class);
        $first = $g->node($p, $start);
        if (! $validate) {
            abort_unless(in_array($first['kind'], ['asset', 'system']), 422);
        }
        $limits = app(Settings::class)->all();
        $depth = min($depth ?? $limits['max_depth'], $limits['max_depth']);
        $max = min($nodeLimit ?? $limits['max_nodes'], $limits['max_nodes']);
        $types = app(Types::class)->all($p);
        $frontier = [$start];
        $seen = [$start => true];
        $paths = [$start => []];
        $nodes = [$start => $first];
        $truncated = false;
        $cycles = false;
        $edges = [];
        $began = microtime(true);
        $queries = 0;
        $redundant = [];
        for ($level = 0; $level < $depth && $frontier; $level++) {
            if ((microtime(true) - $began) * 1000 > $limits['timeout_ms']) {
                $truncated = true;
                break;
            }
            $rows = $g->edges($p)->whereIn('state', ['declared', 'confirmed'])->where(function ($q) use ($types, $frontier) {
                foreach ($types as $key => $t) {
                    if (! $t['impact']) {
                        continue;
                    }$q->orWhere(fn ($q) => $q->where('type_key', $key)->whereIn($t['direction'] === 'reverse' ? 'target' : 'source', $frontier));
                }
            })->orderBy('edge_key')->limit($max * 4 + 1)->get();
            $queries++;
            if ($rows->count() > $max * 4) {
                $truncated = true;
            }$rows = $rows->take($max * 4);
            $keys = [];
            foreach ($rows as $e) {
                $keys[] = $e->source;
                $keys[] = $e->target;
            }$map = $g->nodeMap($p, $keys);
            $next = [];
            $health = app(Sources::class)->health($p, $rows->pluck('relation_uuid')->filter()->all());
            foreach ($rows as $e) {
                if (! isset($map[$e->source],$map[$e->target])) {
                    continue;
                }$t = $types[$e->type_key];
                $from = $t['direction'] === 'reverse' ? $e->target : $e->source;
                $to = $t['direction'] === 'reverse' ? $e->source : $e->target;
                $h = $health[$e->relation_uuid] ?? null;
                $record = (array) $e + ['from' => $from, 'to' => $to, 'unverified' => $e->state !== 'confirmed' || ($h['review_required'] ?? false), 'source_relation' => $e->relation_uuid ?: null];
                $edges[$e->edge_key] = $record;
                if (isset($seen[$to])) {
                    if ($to === $start || in_array($to, array_column($paths[$from] ?? [], 'from'))) {
                        $cycles = true;
                    } else {
                        $redundant[$to] = true;
                    }

                    continue;
                }
                if (count($seen) >= $max) {
                    $truncated = true;

                    continue;
                }$seen[$to] = true;
                $nodes[$to] = $map[$to];
                $paths[$to] = [...($paths[$from] ?? []), $record];
                $next[] = $to;
            }$frontier = array_values(array_unique($next));
        }
        if ($frontier) {
            $truncated = true;
        }
        // A bounded independent incoming-route check highlights redundancy without promising availability.
        $incoming = $g->edges($p)->whereIn('state', ['declared', 'confirmed'])->where(function ($q) use ($types, $seen) {
            foreach ($types as $key => $t) {
                if ($t['impact']) {
                    $q->orWhere(fn ($q) => $q->where('type_key', $key)->whereIn($t['direction'] === 'reverse' ? 'source' : 'target', array_keys($seen)));
                }
            }
        })->limit($max * 4 + 1)->get();
        $queries++;
        $incomingMap = $g->nodeMap($p, $incoming->flatMap(fn ($e) => [$e->source, $e->target])->all());
        $suppliers = [];
        foreach ($incoming->take($max * 4) as $e) {
            if (! isset($incomingMap[$e->source],$incomingMap[$e->target])) {
                continue;
            }$t = $types[$e->type_key];
            $to = $t['direction'] === 'reverse' ? $e->source : $e->target;
            $from = $t['direction'] === 'reverse' ? $e->target : $e->source;
            $suppliers[$to][$from] = true;
        }
        foreach ($suppliers as $k => $s) {
            if (count($s) > 1) {
                $redundant[$k] = true;
            }
        }
        if ($incoming->count() > $max * 4) {
            $truncated = true;
        }
        $connectedRows = $g->edges($p)->where(fn ($q) => $q->where('source', $start)->orWhere('target', $start))->orderBy('edge_key')->limit(61)->get();
        $connectedMap = $g->nodeMap($p, $connectedRows->flatMap(fn ($e) => [$e->source, $e->target])->all());
        $connections = [];
        foreach ($connectedRows->take(60) as $e) {
            $other = $e->source === $start ? $e->target : $e->source;
            if (isset($connectedMap[$e->source],$connectedMap[$e->target])) {
                $connections[] = ['relation' => (array) $e, 'node' => $connectedMap[$other], 'propagates' => $types[$e->type_key]['impact'] ?? false];
            }
        }
        if ((microtime(true) - $began) * 1000 > $limits['timeout_ms']) {
            $truncated = true;
        }

        return ['contract' => 'gnosix.dna.impact.v1', 'connections' => $connections, 'connections_partial' => $connectedRows->count() > 60, 'simulation' => true, 'start' => $start, 'nodes' => array_values($nodes), 'edges' => array_values($edges), 'paths' => $paths, 'direct' => array_values(array_filter(array_keys($paths), fn ($k) => count($paths[$k]) === 1)), 'potential_spaces' => array_values(array_filter($nodes, fn ($n) => $n['kind'] === 'space')), 'potential_equipment' => array_values(array_filter($nodes, fn ($n) => in_array($n['kind'], ['asset', 'system']) && $n['node_key'] !== $start)), 'redundant_routes' => array_keys($redundant), 'cycles_detected' => $cycles, 'truncated' => $truncated, 'unknown_coverage' => true, 'coverage_certified' => false, 'depth' => $depth, 'query_batches' => $queries, 'elapsed_ms' => round((microtime(true) - $began) * 1000, 2), 'notice' => __('dna.simulation_notice')];
    }
}
