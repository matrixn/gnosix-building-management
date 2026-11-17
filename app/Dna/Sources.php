<?php

namespace App\Dna;

use App\Evidence\Engine;
use App\Models\Building;
use App\Models\DnaRelation;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Conflict;
use App\Models\Evidence\Version;
use App\Models\Property;
use App\Models\User;
use App\Support\CapabilityService;
use Illuminate\Support\Facades\Validator;

class Sources
{
    public function canRead(User $u, Property $p): bool
    {
        $c = app(CapabilityService::class);

        return $c->allows($u, $p->organization, 'evidence.upload') || $c->allows($u, $p->organization, 'evidence.review');
    }

    public function attach(User $u, DnaRelation $r, array $sources): void
    {
        Validator::make(['sources' => $sources], ['sources' => 'array|max:10', 'sources.*' => 'array', 'sources.*.version_id' => 'nullable|uuid', 'sources.*.assertion_id' => 'nullable|uuid', 'sources.*.page' => 'nullable|integer|between:1,300'])->validate();
        foreach ($sources as $s) {
            $a = filled($s['assertion_id'] ?? null) ? Assertion::findOrFail($s['assertion_id']) : null;
            $v = filled($s['version_id'] ?? null) ? Version::findOrFail($s['version_id']) : ($a?->version_id ? Version::findOrFail($a->version_id) : null);
            if (! $a && ! $v) {
                continue;
            }
            foreach (array_unique(array_filter([$a?->building_id, $v?->building_id])) as $id) {
                $b = Building::where('property_id', $r->property_id)->findOrFail($id);
                app(Engine::class)->authorize($u, $b, false, 'evidence.read');
            }
            abort_if($a && $v && $a->version_id !== $v->id && ! $a->references()->where('version_id', $v->id)->exists(), 422, __('dna.source_mismatch'));
            $r->sources()->firstOrCreate(['source_uuid' => $a?->id ?? $v->id], ['assertion_id' => $a?->id, 'version_id' => $v?->id, 'source_revision' => $a?->revision, 'page' => $s['page'] ?? $a?->references()->value('page'), 'created_at' => now()]);
        }
    }

    /** Batch source liveness, without reading any private file or disclosing its contents. */
    public function health(Property $p, array $uuids): array
    {
        if (! $uuids) {
            return [];
        }
        $rels = DnaRelation::where('property_id', $p->id)->whereIn('uuid', array_unique($uuids))->with(['sources.version.document', 'sources.assertion'])->get();
        $sources = $rels->flatMap->sources;
        $docIds = $sources->map(fn ($s) => $s->version?->document_id)->filter()->unique();
        $latest = Version::whereIn('document_id', $docIds)->selectRaw('document_id,MAX(number) as newest')->groupBy('document_id')->pluck('newest', 'document_id');
        $ids = $sources->pluck('assertion_id')->filter();
        $conflicts = Conflict::where('status', 'open')->where(fn ($q) => $q->whereIn('assertion_id', $ids)->orWhereIn('existing_assertion_id', $ids))->get();
        $bad = $conflicts->pluck('assertion_id')->merge($conflicts->pluck('existing_assertion_id'))->filter()->flip();
        $buildingIds = $sources->map(fn ($s) => $s->version?->building_id ?? $s->assertion?->building_id)->filter()->unique();
        $live = Building::where('property_id', $p->id)->where('state', '!=', 'archived')->whereIn('id', $buildingIds)->pluck('id')->flip();
        $origins = app(Graph::class)->edges($p)->whereIn('origin_key', $rels->pluck('origin_key')->filter())->pluck('origin_key')->flip();
        $currentNodes = app(Graph::class)->nodeMap($p, $rels->flatMap(fn ($r) => [$r->source, $r->target])->all());
        $types = app(Types::class)->all($p);
        $out = [];
        foreach ($rels as $r) {
            $details = [];
            foreach ($r->sources as $s) {
                $v = $s->version;
                $a = $s->assertion;
                $ok = $live->has($v?->building_id ?? $a?->building_id) && ($v || $a);
                if ($v) {
                    $ok = $ok && $v->document && ! $v->document->archived_at && $v->number == ($latest[$v->document_id] ?? 0);
                }
                if ($s->source_revision !== null) {
                    $ok = $ok && $a && $a->status === 'confirmed' && ! $bad->has($a->id);
                }
                $details[$s->source_uuid] = ['current' => (bool) $ok, 'document_supported' => (bool) $v, 'version_id' => $v?->id, 'assertion_id' => $a?->id, 'building_id' => $v?->building_id ?? $a?->building_id, 'page' => $s->page, 'source_uuid' => $s->source_uuid, 'source_revision' => $a?->revision, 'linked_revision' => $s->source_revision, 'name' => $v?->original_name, 'ai_confidence' => $a?->ai_confidence, 'verification' => $a?->human_verification];
            }
            $basis = $r->review_basis ?? [];
            $originAvailable = $r->backing !== 'adapter' || $origins->has($r->origin_key);
            $definition = $types[$r->type_key] ?? null;
            $compatible = isset($currentNodes[$r->source],$currentNodes[$r->target]) && $definition && in_array($currentNodes[$r->source]['kind'], $definition['source']) && in_array($currentNodes[$r->target]['kind'], $definition['target']);
            $needs = ! $compatible || ! $originAvailable || ($r->state === 'superseded' && $r->backing === 'adapter' && $originAvailable);
            foreach ($basis as $uuid => $rev) {
                if (! ($details[$uuid]['current'] ?? false) || $rev !== $details[$uuid]['source_revision']) {
                    $needs = true;
                }
            }
            if ($r->state === 'confirmed' && ! $basis && $details) {
                $needs = true;
            }
            $out[$r->uuid] = ['sources' => $details, 'origin_available' => $originAvailable, 'review_required' => $needs, 'document_supported' => collect($details)->contains(fn ($s) => $s['current'] && $s['document_supported']), 'valid_basis' => array_keys(array_filter($details, fn ($d) => $d['current']))];
        }

        return $out;
    }

    public function present(User $u, Property $p, array $health): array
    {
        return $this->presentMany($u, $p, ['one' => $health])['one'];
    }

    public function presentMany(User $u, Property $p, array $health): array
    {
        $allowed = $this->canRead($u, $p);
        $ids = [];
        foreach ($health as $h) {
            foreach ($h['sources'] as $source) {
                $ids[] = $source['building_id'];
            }
        }
        $buildingMap = $allowed ? Building::where('property_id', $p->id)->whereIn('id', array_unique($ids))->pluck('uuid', 'id') : collect();
        $out = [];
        foreach ($health as $key => $h) {
            $items = [];
            if ($allowed) {
                foreach ($h['sources'] as $source) {
                    $source['url'] = isset($buildingMap[$source['building_id']]) ? route('app.evidence.show', [$p->organization, $p, $buildingMap[$source['building_id']], 'version' => $source['version_id'], 'fact' => $source['assertion_id'], 'page' => $source['page']]) : null;
                    unset($source['building_id']);
                    $items[] = $source;
                }
            }$out[$key] = ['restricted' => ! $allowed, 'sources' => $items, 'review_required' => $h['review_required'], 'document_supported' => $h['document_supported']];
        }

        return $out;
    }
}
