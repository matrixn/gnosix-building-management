<?php

namespace App\Http\Controllers\Identity;

use App\Dna\Authorization;
use App\Dna\Graph;
use App\Dna\Impact;
use App\Dna\Insights;
use App\Dna\Relations;
use App\Dna\Sources;
use App\Dna\Types;
use App\Http\Controllers\Controller;
use App\Models\DnaInsightDecision;
use App\Models\DnaRelation;
use App\Models\Evidence\Assertion;
use App\Models\Evidence\Version;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DnaController extends Controller
{
    private function property(Request $r, Organization $o, Property $p, string $ability = 'view'): void
    {
        abort_unless($p->organization_id === $o->id, 404);
        app(Authorization::class)->check($r->user('web'), $p, $ability);
    }

    private function filters(Request $r): array
    {
        return $r->validate(['focus' => 'nullable|string|max:80', 'type' => 'nullable|string|max:60', 'page' => 'nullable|integer|between:1,100000', 'q' => 'nullable|string|max:100', 'kind' => 'nullable|in:property,building,floor,space,system,asset', 'building' => 'nullable|string|max:80', 'simulate' => 'nullable|boolean', 'depth' => 'nullable|integer|between:1,12', 'relation' => 'nullable|string|max:180', 'insights_page' => 'nullable|integer|between:1,100000']);
    }

    public function index(Request $r, Organization $organization)
    {
        Gate::forUser($r->user('web'))->authorize('view', $organization);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $organization, 'dna.view'), 403);
        $d = $this->filters($r);
        $r->validate(['property' => 'nullable|integer']);
        $property = $r->integer('property') ? $organization->properties()->findOrFail($r->integer('property')) : null;
        if (! $property) {
            return view('dna.properties', ['organization' => $organization, 'properties' => app(PropertyPermissions::class)->visible($r->user('web'), $organization)->orderBy('name')->paginate(24)]);
        }
        $this->property($r, $organization, $property);
        $u = $r->user('web');
        $focus = $d['focus'] ?? 'property:'.$property->uuid;
        $g = app(Graph::class);
        $graph = $g->neighborhood($u, $property, $focus, $d['page'] ?? 1, $d['type'] ?? null);

        $canManage = $this->allows($u, $property, 'manage');
        $canReview = $this->allows($u, $property, 'review');
        $insightBuilding = $d['building'] ?? ($graph['focus']['kind'] === 'building' ? $focus : null);
        $insightScope = $insightBuilding ? $g->node($property, $insightBuilding)['name'] : $property->name;
        $insights = app(Insights::class)->scan($u, $property, $insightBuilding, $d['insights_page'] ?? 1);
        $relations = DnaRelation::where('property_id', $property->id)->latest('id')->paginate(12, ['*'], 'relations_page')->withQueryString();
        $relationNodes = $g->nodeMap($property, $relations->flatMap(fn ($r) => [$r->source, $r->target])->all());
        $relationHealth = app(Sources::class)->health($property, $relations->pluck('uuid')->all());
        $selected = $this->detail($r, $organization, $property, $d['relation'] ?? null);
        $node = $g->inspect($u, $property, $focus);
        $simulation = ! empty($d['simulate']) ? app(Impact::class)->run($u, $property, $focus, $d['depth'] ?? null) : null;
        $extraKeys = array_filter([old('source'), old('target'), $selected['source'] ?? null, $selected['target'] ?? null], fn ($v) => is_string($v) && strlen($v) <= 80);
        $relationOptions = array_values(collect([...$graph['nodes'], ...array_values($g->nodeMap($property, $extraKeys))])->keyBy('node_key')->all());
        $decisions = DnaInsightDecision::where('property_id', $property->id)->latest('updated_at')->paginate(10, ['*'], 'decisions_page')->withQueryString();

        return view('dna.index', compact('organization', 'property', 'graph', 'canManage', 'canReview', 'insights', 'relations', 'relationNodes', 'relationHealth', 'selected', 'node', 'simulation', 'decisions', 'relationOptions', 'insightBuilding', 'insightScope'));
    }

    private function allows(User $u, Property $p, string $ability): bool
    {
        try {
            app(Authorization::class)->check($u, $p, $ability);

            return true;
        } catch (AuthorizationException|HttpException $e) {
            return false;
        }
    }

    public function graph(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property);
        $d = $this->filters($r);

        return response()->json(app(Graph::class)->neighborhood($r->user('web'), $property, $d['focus'] ?? 'property:'.$property->uuid, $d['page'] ?? 1, $d['type'] ?? null));
    }

    public function search(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property);
        $d = $this->filters($r);
        $g = app(Graph::class);
        if (isset($d['building'])) {
            $g->node($property, $d['building']);
        }$rows = $g->nodes($property)->when($d['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$s.'%')->orWhere('reference', 'like', '%'.$s.'%')))->when($d['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))->when($d['building'] ?? null, fn ($q, $k) => $q->where('building_key', $k))->orderBy('name')->offset((($d['page'] ?? 1) - 1) * 20)->limit(21)->get();

        return response()->json(['contract' => 'gnosix.dna.nodes.v1', 'items' => $rows->take(20)->values(), 'more' => $rows->count() > 20]);
    }

    public function sources(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property);
        abort_unless(app(Sources::class)->canRead($r->user('web'), $property), 403);
        $d = $r->validate(['kind' => 'required|in:version,assertion', 'q' => 'nullable|string|max:100']);
        $column = $d['kind'] === 'version' ? 'original_name' : 'original_value';
        $class = $d['kind'] === 'version' ? Version::class : Assertion::class;
        $items = $class::whereIn('building_id', $property->buildings()->where('state', '!=', 'archived')->select('id'))->when($d['q'] ?? null, fn ($q, $v) => $q->where($column, 'like', '%'.$v.'%'))->latest('id')->limit(20)->get()->map(fn ($x) => ['id' => $x->id, 'label' => $d['kind'] === 'version' ? $x->original_name : $x->semantic_key.' · '.mb_substr($x->original_value, 0, 100)]);

        return response()->json(['items' => $items]);
    }

    public function node(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property);
        $d = $r->validate(['key' => 'required|string|max:80']);

        return response()->json(app(Graph::class)->inspect($r->user('web'), $property, $d['key']));
    }

    public function relation(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property);
        $d = $r->validate(['key' => 'required|string|max:180']);

        return response()->json($this->detail($r, $organization, $property, $d['key']));
    }

    private function detail(Request $r, Organization $o, Property $p, ?string $key): ?array
    {
        if (! $key) {
            return null;
        }$g = app(Graph::class);
        $model = DnaRelation::where('property_id', $p->id)->where('uuid', $key)->first();
        $edge = $model ? ['edge_key' => $model->uuid, 'source' => $model->source, 'target' => $model->target, 'type_key' => $model->type_key, 'revision' => $model->revision, 'state' => $model->state, 'relation_uuid' => $model->uuid, 'backing' => $model->backing] : (array) $g->edges($p)->where('edge_key', $key)->firstOrFail();
        $map = $g->nodeMap($p, [$edge['source'], $edge['target']]);
        if (! $model) {
            abort_unless(isset($map[$edge['source']],$map[$edge['target']]), 404);
        }
        $edge['source_node'] = $map[$edge['source']] ?? null;
        $edge['target_node'] = $map[$edge['target']] ?? null;
        $edge['type'] = app(Types::class)->all($p)[$edge['type_key']];
        $edge['declaration'] = $model?->declaration;
        $edge['can_manage'] = $this->allows($r->user('web'), $p, 'manage');
        $edge['can_review'] = $this->allows($r->user('web'), $p, 'review');
        $edge['history'] = [];
        $edge['evidence'] = null;
        if ($model) {
            $edge['evidence'] = app(Sources::class)->present($r->user('web'), $p, app(Sources::class)->health($p, [$model->uuid])[$model->uuid]);
            $revisions = $model->revisions()->latest('revision')->limit(10)->get();
            $names = User::whereIn('id', [...$revisions->pluck('actor_id'), $model->created_by, $model->reviewed_by])->pluck('name', 'id');
            $edge['author'] = $names[$model->created_by] ?? null;
            $edge['reviewer'] = $names[$model->reviewed_by] ?? null;
            $edge['created_at'] = $model->created_at->utc()->toDateTimeString().' UTC';
            $edge['reviewed_at'] = $model->reviewed_at?->utc()->toDateTimeString();
            $edge['history'] = $revisions->map(fn ($h) => ['revision' => $h->revision, 'at' => $h->created_at, 'state' => $h->snapshot['state'], 'declaration' => $h->snapshot['declaration'], 'reason' => $h->snapshot['decision_reason'] ?? null, 'reviewed_at' => $h->snapshot['reviewed_at'] ?? null, 'actor_id' => $h->actor_id, 'actor' => $names[$h->actor_id] ?? null])->all();
        }
        $s = $map[$edge['source']] ?? null;
        $edge['canonical_edit'] = $s && in_array($s['kind'], ['asset', 'system']) && $edge['can_manage'] ? route('app.assets.edit', [$o, $s['uuid']]) : null;

        return $edge;
    }

    public function simulate(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property);
        $d = $r->validate(['start' => 'required|string|max:80', 'depth' => 'nullable|integer|between:1,12']);
        $data = app(Impact::class)->run($r->user('web'), $property, $d['start'], $d['depth'] ?? null);
        $ids = array_filter(array_column($data['edges'], 'relation_uuid'));
        $health = app(Sources::class)->health($property, $ids);
        $data['sources'] = app(Sources::class)->presentMany($r->user('web'), $property, $health);

        return response()->json($data);
    }

    private function relationBack(Organization $o, Property $p, DnaRelation $relation)
    {
        $focus = app(Graph::class)->nodes($p)->where('node_key', $relation->source)->exists() ? $relation->source : 'property:'.$p->uuid;

        return redirect()->route('app.dna.index', [$o, 'property' => $p->id, 'focus' => $focus, 'relation' => $relation->uuid])->with('status', __('dna.saved'));
    }

    private function back(Organization $o, Property $p)
    {
        return redirect()->route('app.dna.index', [$o, 'property' => $p->id])->with('status', __('dna.saved'));
    }

    public function store(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property, 'manage');
        $relation = app(Relations::class)->create($r->user('web'), $property, $r->all());

        return redirect()->route('app.dna.index', [$organization, 'property' => $property->id, 'focus' => $relation->source, 'relation' => $relation->uuid])->with('status', __('dna.saved'));
    }

    public function update(Request $r, Organization $organization, Property $property, string $relation)
    {
        $this->property($r, $organization, $property, 'manage');
        $model = DnaRelation::where('property_id', $property->id)->where('uuid', $relation)->firstOrFail();
        app(Relations::class)->update($r->user('web'), $model, $r->all());

        return $this->relationBack($organization, $property, $model);
    }

    public function review(Request $r, Organization $organization, Property $property, string $relation)
    {
        $this->property($r, $organization, $property, 'review');
        $model = DnaRelation::where('property_id', $property->id)->where('uuid', $relation)->firstOrFail();
        app(Relations::class)->review($r->user('web'), $model, $r->all());

        return $this->relationBack($organization, $property, $model);
    }

    public function type(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property, 'manage');
        app(Types::class)->create($r->user('web'), $property, $r->all());

        return $this->back($organization, $property);
    }

    public function insight(Request $r, Organization $organization, Property $property)
    {
        $this->property($r, $organization, $property, 'review');
        app(Insights::class)->decide($r->user('web'), $property, $r->all());

        return $this->back($organization, $property);
    }
}
