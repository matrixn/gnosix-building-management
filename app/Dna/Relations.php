<?php

namespace App\Dna;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\DnaRelation;
use App\Models\DnaRelationRevision;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Relations
{
    public function audit(?User $u, Property $p, string $action, string $uuid, ?array $before, array $after): void
    {
        $keys = array_filter([$after['source'] ?? null, $after['target'] ?? null], fn ($k) => is_string($k) && str_starts_with($k, 'asset:'));
        $assetIds = Asset::where('property_id', $p->id)->whereIn('uuid', array_map(fn ($k) => substr($k, 6), $keys))->pluck('id')->all();
        foreach ($assetIds ?: [null] as $assetId) {
            AssetAudit::create(['organization_id' => $p->organization_id, 'property_id' => $p->id, 'asset_id' => $assetId, 'actor_id' => $u?->id, 'entity_type' => 'dna', 'entity_uuid' => $uuid, 'action' => 'dna.'.$action, 'before_data' => $before, 'after_data' => $after, 'created_at' => now()]);
        }
    }

    private function snapshot(DnaRelation $r, User $u, string $action, ?array $before = null, ?string $reason = null): void
    {
        $data = $r->attributesToArray();
        if ($reason !== null) {
            $data['decision_reason'] = $reason;
        }$data['sources'] = $r->sources()->get()->map->attributesToArray()->all();
        DnaRelationRevision::create(['dna_relation_id' => $r->id, 'revision' => $r->revision, 'snapshot' => $data, 'actor_id' => $u->id, 'created_at' => now()]);
        $this->audit($u, $r->property, $action, $r->uuid, $before, $data);
    }

    private function lock(User $u, Property $p, string $ability): void
    {
        Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
        app(Authorization::class)->check($u, $p, $ability);
    }

    private function serves(DnaRelation $r, bool $present): void
    {
        $asset = Asset::where('property_id', $r->property_id)->where('uuid', substr($r->source, 6))->lockForUpdate()->firstOrFail();
        $space = Space::where('property_id', $r->property_id)->where('uuid', substr($r->target, 6))->firstOrFail();
        if ($present) {
            $asset->servedSpaces()->syncWithoutDetaching([$space->id]);
        } else {
            $asset->servedSpaces()->detach($space->id);
        }
        $asset->increment('revision');
    }

    private function validateActive(Property $p, array $s, array $t, string $key, array $type, ?string $except = null): void
    {
        $g = app(Graph::class);
        foreach (['source', 'target'] as $side) {
            $limit = $type['max_'.$side];
            if (! $limit) {
                continue;
            }$q = $g->edges($p)->where('type_key', $key)->where($side, $side === 'source' ? $s['node_key'] : $t['node_key'])->whereIn('state', ['declared', 'confirmed'])->when($except, fn ($q) => $q->where('relation_uuid', '!=', $except));
            if ($q->limit($limit)->count() >= $limit) {
                throw ValidationException::withMessages(['relation' => __('dna.cardinality')]);
            }
        }
        if (! $type['cycles']) {
            // Physical hierarchy is managed by its original Studio; only functional propagation is added here.
            if ($type['impact']) {
                $this->cycleSafe($p, $type['direction'] === 'reverse' ? $t['node_key'] : $s['node_key'], $type['direction'] === 'reverse' ? $s['node_key'] : $t['node_key']);
            } else { // custom non-propagating acyclic types use their own directed edges.
                $frontier = [$t['node_key']];
                $seen = [];
                $cycleBegan = microtime(true);
                for ($i = 0; $i < 64 && $frontier; $i++) {
                    if ((microtime(true) - $cycleBegan) > 2) {
                        throw ValidationException::withMessages(['relation' => __('dna.validation_limit')]);
                    }
                    if (in_array($s['node_key'], $frontier, true)) {
                        throw ValidationException::withMessages(['relation' => __('dna.cycle')]);
                    }$seen = array_fill_keys([...array_keys($seen), ...$frontier], true);
                    $rows = $g->edges($p)->where('type_key', $key)->whereIn('state', ['declared', 'confirmed'])->where(function ($q) use ($frontier, $type) {
                        $q->whereIn('source', $frontier);
                        if ($type['direction'] === 'both') {
                            $q->orWhereIn('target', $frontier);
                        }
                    })->when($except, fn ($q) => $q->where('relation_uuid', '!=', $except))->limit(5001)->get();
                    $next = [];
                    foreach ($rows as $edge) {
                        if (in_array($edge->source, $frontier, true)) {
                            $next[] = $edge->target;
                        }if ($type['direction'] === 'both' && in_array($edge->target, $frontier, true)) {
                            $next[] = $edge->source;
                        }
                    }
                    if (count($next) > 5000 || count($seen) > 5000) {
                        throw ValidationException::withMessages(['relation' => __('dna.validation_limit')]);
                    }$frontier = array_values(array_diff($next, array_keys($seen)));
                }if ($frontier) {
                    throw ValidationException::withMessages(['relation' => __('dna.validation_limit')]);
                }
            }
        }
    }

    private function cycleSafe(Property $p, string $source, string $target): void
    {
        $g = app(Graph::class);
        $types = app(Types::class)->all($p);
        $prefixes = [];
        foreach ($types as $t) {
            if ($t['impact']) {
                foreach ($t[$t['direction'] === 'reverse' ? 'target' : 'source'] as $kind) {
                    $prefixes[$kind === 'system' ? 'asset' : $kind] = true;
                }
            }
        }
        $frontier = [$target];
        $seen = [];
        $began = microtime(true);
        for ($level = 0; $level < 64 && $frontier; $level++) {
            if (in_array($source, $frontier, true)) {
                throw ValidationException::withMessages(['relation' => __('dna.cycle')]);
            }
            if (count($seen) > 5000 || (microtime(true) - $began) > 2) {
                throw ValidationException::withMessages(['relation' => __('dna.validation_limit')]);
            }
            foreach ($frontier as $f) {
                $seen[$f] = true;
            }
            $rows = $g->edges($p)->whereIn('state', ['declared', 'confirmed'])->where(function ($q) use ($types, $frontier, $prefixes, $source) {
                foreach ($types as $key => $t) {
                    if (! $t['impact']) {
                        continue;
                    }$from = $t['direction'] === 'reverse' ? 'target' : 'source';
                    $to = $t['direction'] === 'reverse' ? 'source' : 'target';
                    $q->orWhere(fn ($q) => $q->where('type_key', $key)->whereIn($from, $frontier)->where(function ($q) use ($to, $prefixes, $source) {
                        $q->where($to, $source);
                        foreach (array_keys($prefixes) as $prefix) {
                            $q->orWhere($to, 'like', $prefix.':%');
                        }
                    }));
                }
            })->limit(5001)->get();
            if ($rows->count() > 5000) {
                throw ValidationException::withMessages(['relation' => __('dna.validation_limit')]);
            }
            $map = $g->nodeMap($p, $rows->flatMap(fn ($e) => [$e->source, $e->target])->all());
            $next = [];
            foreach ($rows as $e) {
                if (! isset($map[$e->source],$map[$e->target])) {
                    continue;
                }$to = $types[$e->type_key]['direction'] === 'reverse' ? $e->source : $e->target;
                if (! isset($seen[$to])) {
                    $next[$to] = true;
                }
            }$frontier = array_keys($next);
        }if ($frontier) {
            throw ValidationException::withMessages(['relation' => __('dna.validation_limit')]);
        }
    }

    public function create(User $u, Property $p, array $input): DnaRelation
    {
        return DB::transaction(function () use ($u, $p, $input) {
            $this->lock($u, $p, 'manage');
            $d = Validator::make($input, ['source' => 'required|string|max:80', 'target' => 'required|string|max:80', 'type_key' => 'required|string|max:60', 'declaration' => 'required|string|max:4000', 'proposed' => 'required|boolean', 'contradicts' => 'nullable|uuid', 'sources' => 'nullable|array|max:10'])->validate();
            $types = app(Types::class)->all($p);
            $type = $types[$d['type_key']] ?? null;
            abort_unless($type, 422);
            abort_if(($type['adapter'] ?? false) && $d['type_key'] !== 'serves', 422, __('dna.use_studio'));
            $g = app(Graph::class);
            $s = $g->node($p, $d['source']);
            $t = $g->node($p, $d['target']);
            abort_unless(in_array($s['kind'], $type['source']) && in_array($t['kind'], $type['target']) && $s['node_key'] !== $t['node_key'], 422, __('dna.endpoints_invalid'));
            if ($type['direction'] === 'both' && strcmp($d['source'], $d['target']) > 0 && in_array($t['kind'], $type['source']) && in_array($s['kind'], $type['target'])) {
                [$s,$t] = [$t, $s];
                [$d['source'],$d['target']] = [$d['target'], $d['source']];
            }
            if (DnaRelation::where('property_id', $p->id)->where('type_key', $d['type_key'])->where('source', $d['source'])->where('target', $d['target'])->exists()) {
                throw ValidationException::withMessages(['relation' => __('dna.duplicate')]);
            }
            $contradiction = filled($d['contradicts'] ?? null) ? DnaRelation::where('property_id', $p->id)->where('uuid', $d['contradicts'])->firstOrFail() : null;
            $proposed = (bool) $d['proposed'] || $contradiction || collect($d['sources'] ?? [])->contains(fn ($s) => filled($s['version_id'] ?? null) || filled($s['assertion_id'] ?? null));
            if (! $proposed) {
                $this->validateActive($p, $s, $t, $d['type_key'], $type);
            }
            $r = DnaRelation::create(['uuid' => (string) Str::uuid(), 'organization_id' => $p->organization_id, 'property_id' => $p->id, 'dna_relation_type_id' => $type['custom_id'] ?? null, 'type_key' => $d['type_key'], 'source' => $d['source'], 'target' => $d['target'], 'declaration' => $d['declaration'], 'state' => $proposed ? 'proposed' : 'declared', 'created_by' => $u->id, 'contradicts_id' => $contradiction?->id]);
            app(Sources::class)->attach($u, $r, $d['sources'] ?? []);
            if ($d['type_key'] === 'serves' && ! $proposed) {
                $this->serves($r, true);
                $r->backing = 'adapter';
                $r->origin_key = 'serves:'.$s['uuid'].':'.$t['uuid'];
                DB::table('dna_relations')->where('id', $r->id)->update(['backing' => $r->backing, 'origin_key' => $r->origin_key]);
            }
            $this->snapshot($r, $u, 'created');

            return $r;
        });
    }

    public function update(User $u, DnaRelation $r, array $input): void
    {
        DB::transaction(function () use ($u, $r, $input) {
            $this->lock($u, $r->property, 'manage');
            $r = DnaRelation::lockForUpdate()->findOrFail($r->id);
            $d = Validator::make($input, ['revision' => 'required|integer|min:1', 'declaration' => 'required|string|max:4000', 'sources' => 'nullable|array|max:10'])->validate();
            $this->revision($r, $d['revision']);
            if (in_array($r->state, ['confirmed', 'superseded'])) {
                throw ValidationException::withMessages(['relation' => __('dna.confirmed_immutable')]);
            }
            $old = $r->attributesToArray();
            $r->declaration = $d['declaration'];
            app(Sources::class)->attach($u, $r, $d['sources'] ?? []);
            $r->revision++;
            $r->save();
            $this->snapshot($r, $u, 'updated', $old);
        });
    }

    private function revision(DnaRelation $r, int $n): void
    {
        if ($r->revision !== $n) {
            throw ValidationException::withMessages(['revision' => __('dna.stale')]);
        }
    }

    public function review(User $u, DnaRelation $r, array $input): void
    {
        DB::transaction(function () use ($u, $r, $input) {
            $this->lock($u, $r->property, 'review');
            $r = DnaRelation::lockForUpdate()->findOrFail($r->id);
            $d = Validator::make($input, ['revision' => 'required|integer|min:1', 'state' => 'required|in:confirmed,disputed,superseded,proposed', 'reason' => 'required|string|max:2000'])->validate();
            $this->revision($r, $d['revision']);
            if (($r->state === 'superseded' && $d['state'] !== 'proposed') || ($d['state'] === 'proposed' && $r->state !== 'superseded') || $r->state === $d['state']) {
                throw ValidationException::withMessages(['state' => __('dna.invalid_transition')]);
            }
            $old = $r->attributesToArray();
            $s = $t = null;
            if ($d['state'] === 'confirmed') {
                $g = app(Graph::class);
                $s = $g->node($r->property, $r->source);
                $t = $g->node($r->property, $r->target);
                $type = app(Types::class)->all($r->property)[$r->type_key];
                abort_unless(in_array($s['kind'], $type['source']) && in_array($t['kind'], $type['target']), 422, __('dna.endpoints_invalid'));
                if ($r->contradicts_id && DnaRelation::whereKey($r->contradicts_id)->whereIn('state', ['declared', 'confirmed'])->exists()) {
                    throw ValidationException::withMessages(['relation' => __('dna.contradiction_review')]);
                }
                $this->validateActive($r->property, $s, $t, $r->type_key, $type, $r->uuid);
                $health = app(Sources::class)->health($r->property, [$r->uuid])[$r->uuid];
                if ($health['sources']) {
                    abort_unless(app(Sources::class)->canRead($u, $r->property), 403);
                }if ($health['sources'] && ! $health['valid_basis']) {
                    throw ValidationException::withMessages(['sources' => __('dna.source_not_ready')]);
                }
                $r->review_basis = [];
                foreach ($health['valid_basis'] as $id) {
                    $r->review_basis = [...$r->review_basis, $id => $health['sources'][$id]['source_revision']];
                }
                if ($r->type_key === 'serves') {
                    $this->serves($r, true);
                    $r->backing = 'adapter';
                    if (! $r->origin_key) {
                        $origin = 'serves:'.$s['uuid'].':'.$t['uuid'];
                        DB::table('dna_relations')->where('id', $r->id)->update(['origin_key' => $origin]);
                        $r->origin_key = $origin;
                        $r->syncOriginalAttribute('origin_key');
                    }
                }
            }
            if ($d['state'] === 'superseded' && $r->backing === 'adapter') {
                // If an endpoint was explicitly purged, its UUID/history survives but no current pivot exists.
                if (app(Graph::class)->nodeMap($r->property, [$r->source, $r->target]) && Space::where('property_id', $r->property_id)->where('uuid', substr($r->target, 6))->exists()) {
                    $this->serves($r, false);
                }
            }
            if ($d['state'] === 'proposed') {
                $r->review_basis = null;
            }
            $r->state = $d['state'];
            $r->reviewed_by = $u->id;
            $r->reviewed_at = now();
            $r->revision++;
            $r->save();
            $this->snapshot($r, $u, 'reviewed', $old, $d['reason']);
            // The review reason is itself append-only in the audit and revision, not mixed into the user's declaration.
            $this->audit($u, $r->property, 'decision', $r->uuid, null, ['source' => $r->source, 'state' => $r->state, 'reason' => $d['reason'], 'revision' => $r->revision]);
        });
    }
}
