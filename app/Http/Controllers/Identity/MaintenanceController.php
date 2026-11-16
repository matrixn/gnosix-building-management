<?php

namespace App\Http\Controllers\Identity;

use App\Evidence\Engine;
use App\Http\Controllers\Controller;
use App\Maintenance\Documents;
use App\Maintenance\Settings;
use App\Maintenance\Workflow;
use App\Models\Building;
use App\Models\Evidence\Conflict;
use App\Models\Evidence\Version;
use App\Models\MaintenanceIssue;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class MaintenanceController extends Controller
{
    private function org(Request $r, Organization $o): void
    {
        Gate::forUser($r->user('web'))->authorize('view', $o);
        abort_unless(app(CapabilityService::class)->allows($r->user('web'), $o, 'hospitality.maintenance'), 403);
    }

    private function prop(Request $r, Organization $o, int $id, bool $write = false): Property
    {
        $p = $o->properties()->findOrFail($id);
        app(Workflow::class)->allowed($r->user('web'), $p, $write);

        return $p;
    }

    private function record(Request $r, Organization $o, string $kind, string $uuid, string $ability = 'view'): MaintenanceRecord
    {
        $this->org($r, $o);
        $class = match ($kind) {
            'issue' => MaintenanceIssue::class,'work' => WorkOrder::class,'plan' => MaintenancePlan::class,default => abort(404)
        };
        $m = $class::where('organization_id', $o->id)->where('uuid', $uuid)->firstOrFail();
        // A field invitation grants the restricted portal, not the general maintenance views/files.
        abort_unless(app(Workflow::class)->policy()->report($r->user('web'), $m->property), 403);
        Gate::forUser($r->user('web'))->authorize($ability, $m);

        return $m;
    }

    private function redirect(Organization $o, MaintenanceRecord $m)
    {
        return redirect()->route('app.maintenance.'.$m->kind().'.show', [$o, $m->uuid])->with('status', __('maintenance.saved'));
    }

    private function scope(Request $r, Organization $o, string $class)
    {
        $p = app(PropertyPermissions::class)->visible($r->user('web'), $o);

        return $class::where('organization_id', $o->id)->whereIn('property_id', $p->select('id'))->available()->when($r->integer('group'), fn ($q, $id) => $q->whereHas('property.groups', fn ($q) => $q->where('workspace_groups.organization_id', $o->id)->where('workspace_groups.id', $id)))->when($r->integer('property'), fn ($q, $id) => $q->where('property_id', $id))->when($r->integer('building'), fn ($q, $id) => $q->where(fn ($q) => $q->where('building_id', $id)->orWhereHas('asset', fn ($q) => $q->where('building_id', $id))->orWhereHas('asset.servedSpaces', fn ($q) => $q->where('building_id', $id))))->when($r->integer('space'), fn ($q, $id) => $q->where('space_id', $id))->when($r->integer('asset'), fn ($q, $id) => $q->where('asset_id', $id));
    }

    public function index(Request $r, Organization $organization)
    {
        $this->org($r, $organization);
        $r->validate(['q' => 'nullable|string|max:160', 'group' => 'nullable|integer', 'property' => 'nullable|integer', 'building' => 'nullable|integer', 'space' => 'nullable|integer', 'asset' => 'nullable|integer', 'state' => 'nullable|string|max:30', 'tab' => 'nullable|in:issues,work,plans', 'view' => 'nullable|in:list,board', 'category' => 'nullable|string|max:30', 'priority' => 'nullable|string|max:20', 'assigned' => 'nullable|in:me,unassigned', 'overdue' => 'nullable|boolean']);
        $u = $r->user('web');
        $repeatRules = app(Settings::class)->all();
        $issues = $this->scope($r, $organization, MaintenanceIssue::class)->where(fn ($q) => $q->where('state', '!=', 'draft')->orWhere('created_by', $u->id)->orWhereIn('property_id', app(PropertyPermissions::class)->visible($u, $organization, true)->select('id')));
        $works = $this->scope($r, $organization, WorkOrder::class);
        $plans = $this->scope($r, $organization, MaintenancePlan::class);
        $stats = ['open' => (clone $issues)->whereNotIn('state', ['draft', 'closed', 'cancelled'])->count(), 'critical' => (clone $issues)->where('severity', 'critical')->whereNotIn('state', ['draft', 'closed', 'cancelled'])->count(), 'unassigned' => (clone $works)->whereNull('assigned_to')->whereNull('team')->whereNotIn('state', ['closed', 'cancelled'])->count(), 'overdue' => (clone $works)->where('due_at', '<', now())->whereNotIn('state', ['resolved', 'verified', 'closed', 'cancelled'])->count(), 'upcoming' => (clone $plans)->where('state', 'active')->whereBetween('next_at', [now(), now()->addDays(30)])->count(), 'affected' => (clone $issues)->whereNotIn('state', ['draft', 'closed', 'cancelled'])->whereNotNull('space_id')->distinct()->count('space_id')];
        $recent = (clone $works)->whereNotNull('resolved_at')->orderByDesc('resolved_at')->limit(5)->get();
        $repeats = (clone $works)->whereNotNull('asset_id')->whereIn('state', ['resolved', 'verified', 'closed'])->where('resolved_at', '>=', now()->subDays(app(Settings::class)->all()['repeat_days']))->select('asset_id', 'category')->selectRaw('COUNT(*) AS total')->groupBy('asset_id', 'category')->havingRaw('COUNT(*) >= ?', [$repeatRules['repeat_threshold']])->with('asset')->limit(10)->get();
        $editorProps = app(PropertyPermissions::class)->visible($u, $organization, true)->select('id');
        $costs = (clone $works)->whereIn('property_id', $editorProps)->whereNotNull('currency')->select('currency')->selectRaw('SUM(labor_cost) as labor, SUM(external_cost) as external, SUM(materials_cost) as materials')->groupBy('currency')->get();
        $evidence = app(CapabilityService::class)->allows($u, $organization, 'evidence.review') || app(CapabilityService::class)->allows($u, $organization, 'evidence.upload');
        $conflicts = $evidence ? Conflict::where('status', 'open')->whereIn('building_id', Building::whereIn('property_id', app(PropertyPermissions::class)->visible($u, $organization)->select('id'))->when($r->integer('group'), fn ($q, $id) => $q->whereHas('property.groups', fn ($q) => $q->where('workspace_groups.id', $id)->where('workspace_groups.organization_id', $organization->id)))->when($r->integer('property'), fn ($q, $id) => $q->where('property_id', $id))->when($r->integer('building'), fn ($q, $id) => $q->whereKey($id))->select('id'))->count() : null;
        $selectedGroup = $organization->groups()->whereHas('properties', fn ($q) => $q->whereIn('properties.id', app(PropertyPermissions::class)->visible($u, $organization)->select('id')))->find($r->integer('group'));
        $selectedProperty = app(PropertyPermissions::class)->visible($u, $organization)->find($r->integer('property'));
        $selectedBuilding = Building::whereIn('property_id', app(PropertyPermissions::class)->visible($u, $organization)->select('id'))->find($r->integer('building'));
        $tab = $r->input('tab', 'work');
        $query = match ($tab) {
            'issues' => $issues,'plans' => $plans,default => $works
        };
        $records = $query->when($r->input('q'), fn ($q, $text) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$text.'%')->orWhere('reference', 'like', '%'.$text.'%')))->when($r->input('state'), fn ($q, $s) => $q->where('state', $s))->when($r->input('category'), fn ($q, $s) => $q->where('category', $s))->when($tab === 'work' && $r->input('priority'), fn ($q) => $q->where('priority', $r->input('priority')))->when($tab === 'work' && $r->input('assigned'), fn ($q) => $r->input('assigned') === 'me' ? $q->where('assigned_to', $u->id) : $q->whereNull('assigned_to')->whereNull('team'))->when($tab === 'work' && $r->boolean('overdue'), fn ($q) => $q->where('due_at', '<', now())->whereNotIn('state', ['resolved', 'verified', 'closed', 'cancelled']))->with(['property', 'building', 'space', 'asset', 'assignee'])->orderByDesc('id')->paginate(24)->withQueryString();
        $board = $r->input('view') === 'board' && $tab === 'work';

        return view('maintenance.index', compact('organization', 'stats', 'records', 'tab', 'board', 'recent', 'repeats', 'costs', 'conflicts', 'selectedProperty', 'selectedBuilding', 'selectedGroup', 'repeatRules'));
    }

    public function form(Request $r, Organization $organization)
    {
        $kind = $r->route('kind');
        $uuid = $r->route('uuid');
        $this->org($r, $organization);
        $r->validate(['property' => 'nullable|integer', 'building' => 'nullable|integer', 'space' => 'nullable|integer', 'asset' => 'nullable|integer', 'issue' => 'nullable|integer']);
        if ($r->filled('space_uuid')) {
            $d = $r->validate(['space_uuid' => 'required|uuid', 'property' => 'required|integer']);
            $p = $this->prop($r, $organization, (int) $d['property']);
            $s = $p->spaces()->where('uuid', $d['space_uuid'])->firstOrFail();
            $r->merge(['space' => $s->id]);
        }
        $record = $uuid ? $this->record($r, $organization, $kind, $uuid, 'update') : match ($kind) {
            'issue' => new MaintenanceIssue,'work' => new WorkOrder,'plan' => new MaintenancePlan,default => abort(404)
        };
        $property = $record->exists ? $record->property : ($r->integer('property') ? $this->prop($r, $organization, $r->integer('property'), $kind !== 'issue') : null);
        if ($property) {
            app(Workflow::class)->allowed($r->user('web'), $property, $kind !== 'issue');
            if (! $record->exists) {
                $record->fill(app(Workflow::class)->location($property, ['building_id' => $r->input('building'), 'space_id' => $r->input('space'), 'asset_id' => $r->input('asset')]));
                if ($r->integer('issue')) {
                    $i = MaintenanceIssue::where('property_id', $property->id)->findOrFail($r->integer('issue'));
                    Gate::forUser($r->user('web'))->authorize('view', $i);
                    $record->fill(['title' => $i->title, 'description' => $i->description, 'category' => $i->category, 'building_id' => $i->building_id, 'space_id' => $i->space_id, 'asset_id' => $i->asset_id]);
                    $record->setRelation('issues', collect([$i]));
                }
            }
        }

        if ($property && $r->session()->hasOldInput()) {
            foreach (['building' => Building::class, 'space' => Space::class, 'asset' => Asset::class] as $relation => $model) {
                $id = old($relation.'_id');
                if ($id === null || (is_scalar($id) && ctype_digit((string) $id))) {
                    $selected = $id ? $model::where('property_id', $property->id)->find($id) : null;
                    if (! $selected || ! ($selected->building_id ?? null) || $selected->building()->exists()) {
                        $record->{$relation.'_id'} = $selected?->id;
                        $record->setRelation($relation, $selected);
                    }
                }
            }
            $id = old('assigned_to');
            if ($kind !== 'issue' && is_scalar($id) && ctype_digit((string) $id)) {
                $selected = User::find($id);
                if ($selected && app(Workflow::class)->policy()->report($selected, $property)) {
                    $record->assigned_to = $selected->id;
                    $record->setRelation('assignee', $selected);
                }
            }
        }
        $existingReports = $property && ($record->asset_id || $record->space_id)
            ? MaintenanceIssue::where('property_id', $property->id)->available()->whereNotIn('state', ['draft', 'closed', 'cancelled'])->where(fn ($q) => $record->asset_id ? $q->where('asset_id', $record->asset_id) : $q->where('space_id', $record->space_id))->orderByDesc('id')->limit(3)->get()
            : collect();

        return view('maintenance.form', compact('organization', 'record', 'property', 'kind', 'existingReports'));
    }

    public function save(Request $r, Organization $organization)
    {
        $kind = $r->route('kind');
        $uuid = $r->route('uuid');
        $this->org($r, $organization);
        $r->validate(['property_id' => 'required|integer']);
        $p = $this->prop($r, $organization, $r->integer('property_id'), $kind !== 'issue');
        $m = $uuid ? $this->record($r, $organization, $kind, $uuid, 'update') : null;
        abort_if($m && $m->property_id !== $p->id, 404);
        $r->session()->setPreviousUrl(route('app.maintenance.'.$kind.($uuid ? '.edit' : '.create'), array_filter([$organization, $uuid])).($uuid ? '' : '?property='.$p->id));
        if ($kind !== 'issue') {
            $r->validate(['checklist_text' => 'nullable|string|max:8000']);
            $tasks = array_values(array_filter(array_map('trim', explode("\n", $r->input('checklist_text') ?? ''))));
            $prior = collect($m?->checklist ?? [])->keyBy('text');
            $r->merge(['checklist' => array_map(fn ($text) => ['text' => $text, 'done' => (bool) ($prior[$text]['done'] ?? false)], $tasks)]);
        }
        $m = app(Workflow::class)->{match ($kind) {
            'issue' => 'issue','work' => 'work','plan' => 'plan'
        }}($r->user('web'), $p, $r->all(), $m);

        return $this->redirect($organization, $m);
    }

    public function show(Request $r, Organization $organization)
    {
        $kind = $r->route('kind');
        $uuid = $r->route('uuid');
        $repeatRules = app(Settings::class)->all();
        $record = $this->record($r, $organization, $kind, $uuid);
        $u = $r->user('web');
        $manager = app(Workflow::class)->policy()->manage($u, $record->property);
        $history = $record->history()->with('actor')->paginate(20, ['*'], 'history_page');
        $attachments = $kind === 'plan' ? collect() : $record->attachments()->with('version')->paginate(10, ['*'], 'files_page');
        $comments = $kind === 'work' ? $record->comments()->where(fn ($q) => $manager ? $q : $q->where('private', false)->orWhere('user_id', $u->id))->with('user')->orderByDesc('id')->paginate(15, ['*'], 'comments_page') : null;
        $related = match ($kind) {
            'issue' => $record->workOrders()->orderByDesc('id')->paginate(10, ['*'], 'related_page'),'plan' => $record->workOrders()->orderByDesc('id')->paginate(10, ['*'], 'related_page'),default => $record->issues()->orderByDesc('id')->paginate(10, ['*'], 'related_page')
        };
        $support = $kind === 'plan' ? collect() : app(Workflow::class)->repeats($record)->orderByDesc('resolved_at')->limit(10)->get();
        $repeatCount = $kind === 'plan' ? 0 : app(Workflow::class)->repeats($record)->count();
        $previous = $record->asset_id ? WorkOrder::where('property_id', $record->property_id)->available()->where('asset_id', $record->asset_id)->whereNotNull('resolved_at')->orderByDesc('resolved_at')->limit(5)->get() : collect();
        $evidence = $record->building && (app(CapabilityService::class)->allows($u, $organization, 'evidence.upload') || app(CapabilityService::class)->allows($u, $organization, 'evidence.review'));
        $conflicts = $evidence ? Conflict::where('building_id', $record->building_id)->where('status', 'open')->count() : null;
        $reportRepeats = $kind === 'issue' ? MaintenanceIssue::where('property_id', $record->property_id)->available()->where('state', '!=', 'draft')->where('category', $record->category)->where('id', '!=', $record->id)->where('reported_at', '>=', now()->subDays(app(Settings::class)->all()['repeat_days']))->where(function ($q) use ($record) {
            if ($record->asset_id) {
                $q->where('asset_id', $record->asset_id);
            } elseif ($record->space_id) {
                $q->where('space_id', $record->space_id);
            } else {
                $q->whereRaw('1=0');
            }
        })->orderByDesc('id')->limit(10)->get() : collect();

        return view('maintenance.show', compact('organization', 'record', 'kind', 'manager', 'history', 'attachments', 'comments', 'related', 'support', 'repeatCount', 'previous', 'evidence', 'conflicts', 'reportRepeats', 'repeatRules'));
    }

    public function action(Request $r, Organization $organization)
    {
        $kind = $r->route('kind');
        $uuid = $r->route('uuid');
        $action = $r->route('action');
        $m = $this->record($r, $organization, $kind, $uuid);
        $r->session()->setPreviousUrl(route('app.maintenance.'.$kind.'.show', [$organization, $uuid]));
        $u = $r->user('web');
        $flow = app(Workflow::class);
        if ($action === 'upload') {
            $r->validate(['files' => 'required|array|min:1|max:5', 'files.*' => 'required|file']);
        }
        if ($kind === 'issue' && $action === 'transition') {
            $r->validate(['state' => 'required|string', 'reason' => 'required|string|max:2000']);
        }
        match ($action) {
            'transition' => $kind === 'work' ? $flow->transition($u, $m, $r->all()) : ($kind === 'issue' ? $flow->issueState($u, $m, (int) $r->validate(['revision' => 'required|integer|min:1'])['revision'], (string) $r->input('state'), (string) $r->input('reason')) : $flow->suspend($u, $m, $r->all())), 'perform' => $kind === 'work' ? $flow->perform($u, $m, $r->all()) : abort(404),'comment' => $kind === 'work' ? $flow->comment($u, $m, $r->all()) : abort(404),'upload' => $kind !== 'plan' ? app(Documents::class)->upload($u, $m, $r->file('files', [])) : abort(404),'link' => $kind !== 'plan' ? app(Documents::class)->link($u, $m, $r->validate(['version_id' => 'required|uuid'])['version_id']) : abort(404),'candidate' => $kind === 'work' ? app(Documents::class)->propose($u, $m, $r->all()) : abort(404),default => abort(404)
        };

        return $this->redirect($organization, $m);
    }

    public function file(Request $r, Organization $organization)
    {
        $kind = $r->route('kind');
        $uuid = $r->route('uuid');
        $attachment = (int) $r->route('attachment');
        $m = $this->record($r, $organization, $kind, $uuid);
        abort_unless($kind !== 'plan' && $r->integer('viewer') === $r->user('web')->id, 403);
        $a = $m->attachments()->findOrFail($attachment);
        $v = $a->version;
        abort_unless($v && Building::whereKey($v->building_id)->exists(), 404);
        $disk = Storage::disk('evidence');
        abort_unless($disk->exists($v->path), 404);
        $bytes = $disk->get($v->path);
        abort_unless(hash_equals($v->sha256, hash('sha256', $bytes)), 409);

        return response($bytes, 200, ['Content-Type' => $v->mime, 'Content-Disposition' => HeaderUtils::makeDisposition('attachment', Engine::filename($v->original_name), 'document'), 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    public function lookup(Request $r, Organization $organization)
    {
        $this->org($r, $organization);
        $d = $r->validate(['kind' => 'required|in:property,building,space,asset,member,issue,group,version,work', 'property' => 'nullable|integer', 'building' => 'nullable|integer', 'q' => 'nullable|string|max:160', 'write' => 'nullable|boolean', 'page' => 'nullable|integer|min:1|max:100000']);
        $kind = $d['kind'];
        $u = $r->user('web');
        if ($kind === 'group') {
            $q = $organization->groups()->whereHas('properties', fn ($q) => $q->whereIn('properties.id', app(PropertyPermissions::class)->visible($u, $organization)->select('id')));
        } elseif ($kind === 'property') {
            $q = app(PropertyPermissions::class)->visible($u, $organization, (bool) ($d['write'] ?? false));
        } else {
            $p = $this->prop($r, $organization, (int) ($d['property'] ?? 0));
            $q = match ($kind) {
                'building' => $p->buildings()->where('state', '!=', 'archived'),'space' => $p->spaces()->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building')),'asset' => $p->assets()->available()->whereNull('archived_at'),'member' => User::whereIn('id', $organization->memberships()->select('user_id')),'version' => Version::whereIn('building_id', $p->buildings()->select('id')), 'work' => $p->workOrders()->available()->whereNotIn('state', ['cancelled', 'closed']), 'issue' => $p->maintenanceIssues()->available()->where('state', '!=', 'draft')
            };
            if ($kind === 'version') {
                abort_unless(app(CapabilityService::class)->allows($u, $organization, 'evidence.upload') || app(CapabilityService::class)->allows($u, $organization, 'evidence.review'), 403);
            }
            if ($kind === 'work') {
                app(Workflow::class)->allowed($u, $p, true);
            }
            if ($kind === 'member') {
                app(Workflow::class)->allowed($u, $p, true);
                $q->whereIn('id', $organization->memberships()->where(fn ($q) => $q->where('role', 'owner')->orWhere('access_scope', 'all')->orWhereHas('propertyAccesses', fn ($q) => $q->where('property_id', $p->id)))->select('user_id'));
            }if (isset($d['building']) && in_array($kind, ['space', 'asset', 'issue', 'version'])) {
                $q->where('building_id', $d['building']);
            }
        }
        $column = in_array($kind, ['issue', 'work']) ? 'title' : ($kind === 'version' ? 'original_name' : 'name');
        $rows = $q->when($d['q'] ?? null, fn ($q, $s) => $q->where($column, 'like', '%'.$s.'%'))->orderBy('id')->simplePaginate(20);

        return response()->json(['items' => collect($rows->items())->map(fn ($m) => ['id' => $m->id, 'label' => $m->$column]), 'more' => $rows->hasMorePages()]);
    }
}
