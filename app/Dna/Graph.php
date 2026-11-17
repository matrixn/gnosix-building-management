<?php

namespace App\Dna;

use App\Models\Property;
use App\Models\User;
use App\Support\CapabilityService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Contract v1: UUID node keys; adapters remain the only truth for spatial/service links. */
class Graph
{
    public function cat(string ...$parts): string
    {
        return DB::getDriverName() === 'sqlite' ? '('.implode(' || ', $parts).')' : 'CONCAT('.implode(',', $parts).')';
    }

    private function key(string $type, string $uuid): string
    {
        return $this->cat("'$type:'", $uuid);
    }

    private function buildingAvailable(Builder $q, string $alias = 'b'): Builder
    {
        return $q->whereNull($alias.'.deleted_at')->where($alias.'.state', '!=', 'archived');
    }

    public function nodes(Property $p): Builder
    {
        $pk = $this->key('property', 'p.uuid');
        $bk = $this->key('building', 'b.uuid');
        $fk = $this->key('floor', 'f.uuid');
        $sk = $this->key('space', 's.uuid');
        $ak = $this->key('asset', 'a.uuid');
        $property = DB::table('properties as p')->where('p.id', $p->id)->selectRaw("$pk as node_key,p.uuid,'property' as kind,p.name,'' as reference,p.id as native_id,NULL as parent_key,NULL as building_key");
        $b = $this->buildingAvailable(DB::table('buildings as b')->join('properties as p', 'p.id', '=', 'b.property_id')->where('p.id', $p->id))->selectRaw("$bk as node_key,b.uuid,'building' as kind,b.name,COALESCE(b.reference,'') as reference,b.id as native_id,$pk as parent_key,$bk as building_key");
        $f = $this->buildingAvailable(DB::table('floors as f')->join('buildings as b', 'b.id', '=', 'f.building_id')->where('b.property_id', $p->id)->whereNull('f.archived_at'))->selectRaw("$fk as node_key,f.uuid,'floor' as kind,f.name,'' as reference,f.id as native_id,$bk as parent_key,$bk as building_key");
        $s = DB::table('spaces as s')->join('properties as p', 'p.id', '=', 's.property_id')->leftJoin('buildings as b', 'b.id', '=', 's.building_id')->leftJoin('floors as f', fn ($j) => $j->on('f.id', '=', 's.floor_id')->whereNull('f.archived_at'))->where('s.property_id', $p->id)->whereNull('s.archived_at')->where(fn ($q) => $q->whereNull('s.building_id')->orWhere(fn ($q) => $this->buildingAvailable($q)))->selectRaw("$sk as node_key,s.uuid,'space' as kind,s.name,COALESCE(s.number,'') as reference,s.id as native_id,CASE WHEN f.id IS NOT NULL THEN $fk WHEN b.id IS NOT NULL THEN $bk ELSE $pk END as parent_key,CASE WHEN b.id IS NOT NULL THEN $bk ELSE NULL END as building_key");
        $a = DB::table('assets as a')->join('properties as p', 'p.id', '=', 'a.property_id')->leftJoin('buildings as b', 'b.id', '=', 'a.building_id')->leftJoin('floors as f', fn ($j) => $j->on('f.id', '=', 'a.floor_id')->whereNull('f.archived_at'))->leftJoin('spaces as s', fn ($j) => $j->on('s.id', '=', 'a.space_id')->whereNull('s.archived_at'))->where('a.property_id', $p->id)->whereNull('a.archived_at')->where(fn ($q) => $q->whereNull('a.building_id')->orWhere(fn ($q) => $this->buildingAvailable($q)))->selectRaw("$ak as node_key,a.uuid,CASE WHEN a.is_system=1 THEN 'system' ELSE 'asset' END as kind,a.name,a.reference,a.id as native_id,CASE WHEN s.id IS NOT NULL THEN $sk WHEN f.id IS NOT NULL THEN $fk WHEN b.id IS NOT NULL THEN $bk ELSE $pk END as parent_key,CASE WHEN b.id IS NOT NULL THEN $bk ELSE NULL END as building_key");

        return DB::query()->fromSub($property->unionAll($b)->unionAll($f)->unionAll($s)->unionAll($a), 'dna_nodes');
    }

    public function node(Property $p, string $key): array
    {
        return (array) $this->nodes($p)->where('node_key', $key)->firstOrFail();
    }

    public function nodeMap(Property $p, array $keys): array
    {
        return $this->nodes($p)->whereIn('node_key', array_values(array_unique($keys)))->orderBy('kind')->orderBy('name')->orderBy('node_key')->get()->mapWithKeys(fn ($n) => [$n->node_key => (array) $n])->all();
    }

    public function edges(Property $p): Builder
    {
        $primary = DB::query()->fromSub($this->nodes($p), 'n')->whereNotNull('parent_key')->selectRaw($this->cat("'spatial:'", 'n.node_key')." as edge_key,CASE WHEN kind IN ('asset','system') THEN node_key ELSE parent_key END as source,CASE WHEN kind IN ('asset','system') THEN parent_key ELSE node_key END as target,CASE WHEN kind IN ('asset','system') THEN CASE WHEN parent_key LIKE 'property:%' THEN 'registered_to' ELSE 'located_in' END ELSE 'contains' END as type_key");
        $parts = DB::table('assets as a')->join('assets as parent', 'parent.id', '=', 'a.system_id')->where('a.property_id', $p->id)->where('parent.property_id', $p->id)->selectRaw($this->cat("'part:'", 'a.uuid', "':'", 'parent.uuid').' as edge_key,'.$this->key('asset', 'a.uuid').' as source,'.$this->key('asset', 'parent.uuid')." as target,'part_of' as type_key");
        $served = DB::table('asset_served_spaces as ss')->join('assets as a', 'a.id', '=', 'ss.asset_id')->join('spaces as s', 's.id', '=', 'ss.space_id')->where('a.property_id', $p->id)->where('s.property_id', $p->id)->selectRaw($this->cat("'serves:'", 'a.uuid', "':'", 's.uuid').' as edge_key,'.$this->key('asset', 'a.uuid').' as source,'.$this->key('space', 's.uuid')." as target,'serves' as type_key");
        $adapted = DB::query()->fromSub($primary->unionAll($parts)->unionAll($served), 'e')->leftJoin('dna_relations as r', fn ($j) => $j->on('r.origin_key', '=', 'e.edge_key')->where('r.property_id', $p->id))->selectRaw("COALESCE(r.uuid,e.edge_key) as edge_key,e.source,e.target,e.type_key,CASE WHEN r.state='superseded' THEN 'declared' ELSE COALESCE(r.state,'declared') END as state,COALESCE(r.revision,0) as revision,e.edge_key as origin_key,COALESCE(r.uuid,'') as relation_uuid,'adapter' as backing");
        $extra = DB::table('dna_relations')->where('property_id', $p->id)->where('backing', 'new')->where('state', '!=', 'superseded')->selectRaw('uuid as edge_key,source,target,type_key,state,revision,origin_key,uuid as relation_uuid,backing');

        return DB::query()->fromSub($adapted->unionAll($extra), 'dna_edges');
    }

    public function neighborhood(User $u, Property $p, string $focus, int $page = 1, ?string $type = null): array
    {
        app(Authorization::class)->check($u, $p);
        $node = $this->node($p, $focus);
        $limits = app(Settings::class)->all();
        $batch = min(60, $limits['max_nodes'] - 1);
        $types = app(Types::class)->all($p);
        abort_if($type && ! isset($types[$type]), 422);
        $rows = $this->edges($p)->where(fn ($q) => $q->where('source', $focus)->orWhere('target', $focus))->when($type, fn ($q) => $q->where('type_key', $type))->orderBy('edge_key')->offset(($page - 1) * $batch)->limit($batch + 1)->get();
        $more = $rows->count() > $batch;
        $rows = $rows->take($batch);
        $keys = [$focus];
        foreach ($rows as $e) {
            $keys[] = $e->source;
            $keys[] = $e->target;
        }$nodes = $this->nodeMap($p, $keys);
        $health = app(Sources::class)->health($p, $rows->pluck('relation_uuid')->filter()->all());
        $edges = [];
        foreach ($rows as $e) {
            if (isset($nodes[$e->source],$nodes[$e->target])) {
                $edges[] = (array) $e + ['review_required' => $health[$e->relation_uuid]['review_required'] ?? false, 'document_supported' => $health[$e->relation_uuid]['document_supported'] ?? false];
            }
        }

        return ['contract' => 'gnosix.dna.v1', 'focus' => $node, 'nodes' => array_values($nodes), 'edges' => $edges, 'page' => $page, 'more' => $more, 'next_page' => $more ? $page + 1 : null, 'types' => $types, 'limits' => $limits];
    }

    public function inspect(User $u, Property $p, string $key): array
    {
        app(Authorization::class)->check($u, $p);
        $n = $this->node($p, $key);
        $o = $p->organization;
        $filter = ['property' => $p->id];
        if (in_array($n['kind'], ['building', 'space', 'asset', 'system'])) {
            $filter[$n['kind'] === 'space' ? 'space' : (in_array($n['kind'], ['asset', 'system']) ? 'asset' : 'building')] = $n['native_id'];
        }
        $links = app(CapabilityService::class)->allows($u, $o, 'hospitality.maintenance') ? ['maintenance' => route('app.maintenance.index', [$o, ...$filter])] : [];
        if (in_array($n['kind'], ['asset', 'system'])) {
            $links['passport'] = route('app.assets.show', [$o, $n['uuid']]);
        }
        $b = $n['building_key'] ? substr($n['building_key'], 9) : null;
        if ($b) {
            $links['studio'] = route('app.declarations.show', [$o, $p, $b]);
        }
        if ($b && app(Sources::class)->canRead($u, $p)) {
            $links['evidence'] = route('app.evidence.show', [$o, $p, $b]);
        }

        return $n + ['links' => $links];
    }
}
