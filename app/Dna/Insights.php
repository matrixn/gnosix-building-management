<?php

namespace App\Dna;

use App\Models\DnaInsightDecision;
use App\Models\DnaRelation;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\CapabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Insights
{
    private function item(Property $p, string $code, string $subject, array $refs, string $basis): array
    {
        return ['code' => $code, 'subject' => $subject, 'fingerprint' => hash('sha256', $p->uuid.'|'.$code.'|'.$subject.'|'.$basis), 'message' => __('dna.insight_'.$code), 'references' => $refs, 'state' => 'open', 'reason' => null, 'revision' => 0];
    }

    public function scan(User $u, Property $p, ?string $building = null, int $page = 1, ?string $subject = null): array
    {
        app(Authorization::class)->check($u, $p);
        $g = app(Graph::class);
        if ($building) {
            $b = $g->node($p, $building);
            abort_unless($b['kind'] === 'building', 422);
        }
        $nodes = $g->nodes($p)->whereIn('kind', ['asset', 'system'])->when($building, fn ($q) => $q->where('building_key', $building))->when($subject, fn ($q) => $q->where('node_key', $subject))->orderBy('node_key')->offset($subject ? 0 : ($page - 1) * 16)->limit(17)->get();
        $more = $nodes->count() > 16;
        $nodes = $nodes->take(16);
        $keys = $nodes->pluck('node_key')->all();
        $incident = $keys ? $g->edges($p)->where(fn ($q) => $q->whereIn('source', $keys)->orWhereIn('target', $keys))->whereIn('state', ['declared', 'confirmed'])->limit(2001)->get() : collect();
        $partial = $incident->count() > 2000;
        $incident = $incident->take(2000);
        $neighbors = $g->nodeMap($p, $incident->flatMap(fn ($e) => [$e->source, $e->target])->all());
        $incident = $incident->filter(fn ($e) => isset($neighbors[$e->source],$neighbors[$e->target]));
        $types = app(Types::class)->all($p);
        $maintenance = app(CapabilityService::class)->allows($u, $p->organization, 'hospitality.maintenance');
        $work = $maintenance ? WorkOrder::available()->where('property_id', $p->id)->whereIn('asset_id', $nodes->pluck('native_id'))->where('priority', 'critical')->whereNotIn('state', ['closed', 'cancelled'])->orderBy('id')->limit(40)->get() : collect();
        $items = [];
        foreach ($nodes as $n) {
            $refs = [['kind' => 'node', 'key' => $n->node_key]];
            $own = $incident->filter(fn ($e) => $e->source === $n->node_key || $e->target === $n->node_key);
            $basis = hash('sha256', json_encode($own->sortBy('edge_key')->values()));
            if (! $partial && $n->kind === 'system' && ! $own->contains(fn ($e) => $e->type_key === 'serves' && $e->source === $n->node_key)) {
                $items[] = $this->item($p, 'no_coverage', $n->node_key, $refs, $basis);
            }
            if (! $partial && ! $own->contains(function ($e) use ($n, $types) {
                $t = $types[$e->type_key] ?? null;

                return $t && $t['category'] === 'dependency' && $t['impact'] && ($t['direction'] === 'reverse' ? $e->source : $e->target) === $n->node_key;
            })) {
                $items[] = $this->item($p, 'power_unknown', $n->node_key, $refs, $basis);
            }
            $critical = $work->where('asset_id', $n->native_id);
            if ($critical->isNotEmpty()) {
                $wr = $critical->map(fn ($w) => ['kind' => 'work', 'uuid' => $w->uuid, 'label' => $w->reference, 'url' => route('app.maintenance.work.show', [$p->organization, $w->uuid])])->all();
                $items[] = $this->item($p, 'critical_work', $n->node_key, [...$refs, ...$wr], $basis.'|'.$critical->map(fn ($w) => [$w->uuid, $w->revision])->toJson());
            }
        }
        $relations = DnaRelation::where('property_id', $p->id)->where('state', '!=', 'superseded')->when($subject, fn ($q) => str_starts_with($subject, 'relation:') ? $q->where('uuid', substr($subject, 9)) : $q->where(fn ($q) => $q->where('source', $subject)->orWhere('target', $subject)))->when($building, function ($q) use ($g, $p, $building) {
            $q->where(fn ($q) => $q->whereIn('source', $g->nodes($p)->where('building_key', $building)->select('node_key'))->orWhereIn('target', $g->nodes($p)->where('building_key', $building)->select('node_key')));
        })->orderBy('id')->offset($subject ? 0 : ($page - 1) * 16)->limit(17)->get();
        $more = $more || $relations->count() > 16;
        $relations = $relations->take(16);
        $map = $g->nodeMap($p, $relations->flatMap(fn ($r) => [$r->source, $r->target])->all());
        $health = app(Sources::class)->health($p, $relations->pluck('uuid')->all());
        $contradicted = DB::table('dna_relations')->where('property_id', $p->id)->whereIn('id', $relations->pluck('contradicts_id')->filter())->whereIn('state', ['declared', 'confirmed'])->pluck('id')->flip();
        foreach ($relations as $r) {
            $ref = [['kind' => 'relation', 'uuid' => $r->uuid]];
            $subjectKey = 'relation:'.$r->uuid;
            $basis = (string) $r->revision;
            $h = $health[$r->uuid];
            $sourceFlags = array_map(fn ($s) => $s['current'], $h['sources']);
            ksort($sourceFlags);
            if (! isset($map[$r->source],$map[$r->target])) {
                $items[] = $this->item($p, 'unavailable_endpoint', $subjectKey, $ref, $basis);
            }
            if (! $h['document_supported']) {
                $items[] = $this->item($p, 'missing_evidence', $subjectKey, $ref, $basis);
            }
            if ($h['review_required']) {
                $items[] = $this->item($p, 'source_changed', $subjectKey, $ref, $basis.'|'.json_encode($sourceFlags));
            }
            if ($r->state === 'disputed' || ($r->contradicts_id && $contradicted->has($r->contradicts_id))) {
                $items[] = $this->item($p, 'contradiction', $subjectKey, $ref, $basis);
            }
        }
        $decisions = DnaInsightDecision::where('property_id', $p->id)->whereIn('fingerprint', array_column($items, 'fingerprint'))->get()->keyBy('fingerprint');
        foreach ($items as &$item) {
            if ($decision = $decisions->get($item['fingerprint'])) {
                $item['state'] = $decision->state;
                $item['reason'] = $decision->reason;
                $item['revision'] = $decision->revision;
            }
        }

        return ['contract' => 'gnosix.dna.insights.v1', 'items' => $items, 'page' => $page, 'more' => $more, 'partial' => $partial, 'notice' => __('dna.insight_notice')];
    }

    public function decide(User $u, Property $p, array $input): void
    {
        DB::transaction(function () use ($u, $p, $input) {
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            app(Authorization::class)->check($u, $p, 'review');
            $d = Validator::make($input, ['subject' => 'required|string|max:80', 'fingerprint' => 'required|string|size:64', 'state' => 'required|in:reviewed,dismissed,resolved', 'reason' => 'required|string|max:2000', 'revision' => 'required|integer|min:0'])->validate();
            $items = $this->scan($u, $p, null, 1, $d['subject'])['items'];
            $current = collect($items)->firstWhere('fingerprint', $d['fingerprint']);
            abort_unless($current, 409, __('dna.insight_stale'));
            $record = DnaInsightDecision::where('property_id', $p->id)->where('fingerprint', $d['fingerprint'])->lockForUpdate()->first();
            if (($record?->revision ?? 0) !== (int) $d['revision']) {
                throw ValidationException::withMessages(['revision' => __('dna.stale')]);
            }$before = $record?->attributesToArray();
            $record ??= new DnaInsightDecision(['uuid' => (string) Str::uuid(), 'organization_id' => $p->organization_id, 'property_id' => $p->id, 'fingerprint' => $d['fingerprint'], 'revision' => 0]);
            $record->fill(['state' => $d['state'], 'reason' => $d['reason'], 'reviewed_by' => $u->id, 'snapshot' => $current]);
            $record->revision++;
            $record->save();
            app(Relations::class)->audit($u, $p, 'insight_'.$d['state'], $record->uuid, $before, $record->attributesToArray());
        });
    }
}
