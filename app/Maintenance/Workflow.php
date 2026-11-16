<?php

namespace App\Maintenance;

use App\Field\Access;
use App\Jobs\NotifyMaintenance;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\Building;
use App\Models\MaintenanceIssue;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\MaintenancePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Workflow
{
    public function policy(): MaintenancePolicy
    {
        return app(MaintenancePolicy::class);
    }

    public function allowed(User $u, Property $p, bool $manage = false): void
    {
        abort_unless($this->policy()->{$manage ? 'manage' : 'report'}($u, $p), 403);
    }

    public function audit(MaintenanceRecord $m, ?User $u, string $action, ?array $before = null, ?string $reason = null): void
    {
        $after = $m->attributesToArray();
        AssetAudit::create(['organization_id' => $m->organization_id, 'property_id' => $m->property_id, 'asset_id' => $m->asset_id, 'entity_type' => $m->kind(), 'entity_uuid' => $m->uuid, 'actor_id' => $u?->id, 'action' => 'maintenance.'.$action, 'before_data' => $before, 'after_data' => $after, 'justification' => $reason, 'created_at' => now()]);
    }

    public function lock(MaintenanceRecord $m, int $revision): MaintenanceRecord
    {
        Property::whereKey($m->property_id)->lockForUpdate()->firstOrFail();
        $m = $m->newQuery()->lockForUpdate()->findOrFail($m->id);
        if ($m->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => __('asset.stale')]);
        }

        return $m;
    }

    public function location(Property $p, array $d): array
    {
        $a = isset($d['asset_id']) ? Asset::where('property_id', $p->id)->available()->whereNull('archived_at')->findOrFail($d['asset_id']) : null;
        $s = isset($d['space_id']) ? Space::where('property_id', $p->id)->findOrFail($d['space_id']) : null;
        $bid = $d['building_id'] ?? $s?->building_id ?? $a?->building_id;
        $b = $bid ? Building::where('property_id', $p->id)->where('state', '!=', 'archived')->findOrFail($bid) : null;
        if ($s && $s->building_id !== $b?->id) {
            throw ValidationException::withMessages(['space_id' => __('asset.location_invalid')]);
        }
        if ($a && $s && $a->space_id !== $s->id && ! $a->servedSpaces()->where('spaces.id', $s->id)->exists()) {
            throw ValidationException::withMessages(['asset_id' => __('maintenance.asset_space')]);
        }
        if ($a && $b && $a->building_id !== $b->id && ! $a->servedSpaces()->where('building_id', $b->id)->exists()) {
            throw ValidationException::withMessages(['asset_id' => __('asset.location_invalid')]);
        }

        return ['building_id' => $b?->id, 'space_id' => $s?->id ?? ($a && $a->building_id === $b?->id ? $a->space_id : null), 'asset_id' => $a?->id];
    }

    private function common(array $d): array
    {
        return Validator::make($d, ['title' => 'required|string|max:180', 'description' => 'nullable|string|max:5000', 'category' => ['required', Rule::in(config('maintenance.categories'))], 'building_id' => 'nullable|integer', 'space_id' => 'nullable|integer', 'asset_id' => 'nullable|integer'])->validate();
    }

    public function issue(User $u, Property $p, array $input, ?MaintenanceIssue $issue = null): MaintenanceIssue
    {
        return DB::transaction(function () use ($u, $p, $input, $issue) {
            $this->allowed($u, $p);
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $d = $this->common($input) + Validator::make($input, ['severity' => ['required', Rule::in(config('maintenance.severities'))], 'impact' => ['required', Rule::in(config('maintenance.impacts'))], 'submit' => 'required|boolean', 'revision' => $issue ? 'required|integer|min:1' : 'nullable|integer'])->validate();
            $old = null;
            if ($issue) {
                $issue = $this->lock($issue, (int) $d['revision']);
                Gate::forUser($u)->authorize('update', $issue);
                abort_unless($issue->state === 'draft' && $issue->property_id === $p->id, 403);
                $old = ['state' => $issue->state];
                $issue->revision++;
            } else {
                $issue = new MaintenanceIssue(['organization_id' => $p->organization_id, 'property_id' => $p->id, 'created_by' => $u->id]);
            }
            $issue->fill(array_intersect_key($d, array_flip(['title', 'description', 'category', 'severity', 'impact'])) + $this->location($p, $d));
            $issue->state = $d['submit'] ? 'reported' : 'draft';
            $issue->reported_at = $d['submit'] ? now() : null;
            $issue->save();
            $this->audit($issue, $u, $d['submit'] ? 'reported' : 'draft_saved', $old);

            return $issue;
        });
    }

    public function issueState(User $u, MaintenanceIssue $m, int $rev, string $state, string $reason): void
    {
        DB::transaction(function () use ($u, $m, $rev, $state, $reason) {
            $m = $this->lock($m, $rev);
            Gate::forUser($u)->authorize('view', $m);
            abort_unless($this->policy()->manage($u, $m->property) || ($m->created_by === $u->id && $state === 'reported' && $m->state === 'closed'), 403);
            Validator::make(['state' => $state, 'reason' => $reason], ['state' => 'required|in:reported,triaged,closed,cancelled', 'reason' => 'required|string|max:2000'])->validate();
            if ($state === 'closed' && $m->workOrders()->whereNotIn('state', ['closed', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['state' => __('maintenance.open_interventions')]);
            }
            $before = ['state' => $m->state];
            $m->state = $state;
            $m->closed_at = $state === 'closed' ? now() : null;
            $m->reported_at ??= now();
            $m->revision++;
            $m->save();
            $this->audit($m, $u, 'issue_state', $before, $reason);
        });
    }

    private function assignee(User $u, Property $p, ?int $id): ?int
    {
        if (! $id) {
            return null;
        }$assigned = User::findOrFail($id);
        abort_unless($this->policy()->report($assigned, $p), 422);

        return $assigned->id;
    }

    private function checklist(mixed $items): array
    {
        Validator::make(['checklist' => $items], ['checklist' => 'nullable|array|max:40', 'checklist.*.text' => 'required|string|max:200', 'checklist.*.done' => 'required|boolean'])->validate();

        return array_map(fn ($i) => ['text' => $i['text'], 'done' => (bool) $i['done']], $items ?? []);
    }

    public function work(User $u, Property $p, array $input, ?WorkOrder $work = null): WorkOrder
    {
        return DB::transaction(function () use ($u, $p, $input, $work) {
            $this->allowed($u, $p, true);
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $d = $this->common($input) + Validator::make($input, ['priority' => ['required', Rule::in(config('maintenance.severities'))], 'assigned_to' => 'nullable|integer', 'team' => 'nullable|string|max:120', 'require_photo' => 'sometimes|boolean', 'due_at' => 'nullable|date_format:Y-m-d\\TH:i', 'issue_ids' => 'nullable|array|max:30', 'issue_ids.*' => 'integer|distinct', 'revision' => $work ? 'required|integer|min:1' : 'nullable|integer'])->validate();
            $location = $this->location($p, $d);
            $issues = MaintenanceIssue::where('property_id', $p->id)->available()->where('state', '!=', 'draft')->whereIn('id', $d['issue_ids'] ?? [])->get();
            if ($issues->count() !== count($d['issue_ids'] ?? [])) {
                abort(422);
            }
            foreach ($issues as $i) {
                if ($i->building_id !== $location['building_id']) {
                    throw ValidationException::withMessages(['issue_ids' => __('maintenance.same_building')]);
                }
            }
            $before = null;
            if ($work) {
                $work = $this->lock($work, (int) $d['revision']);
                Gate::forUser($u)->authorize('update', $work);
                $before = ['state' => $work->state];
                if (in_array($work->state, ['verified', 'closed', 'cancelled'])) {
                    abort(422);
                }$work->revision++;
                if ($work->state === 'resolved') {
                    $work->state = 'in_progress';
                    $work->resolved_at = null;
                }
            } else {
                $work = new WorkOrder(['organization_id' => $p->organization_id, 'property_id' => $p->id, 'created_by' => $u->id, 'state' => 'reported']);
            }
            $work->fill(array_intersect_key($d, array_flip(['title', 'description', 'category', 'priority', 'team', 'require_photo'])) + $location);
            $work->assigned_to = $this->assignee($u, $p, $d['assigned_to'] ?? null);
            $work->due_at = filled($d['due_at'] ?? null) ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $d['due_at'], $p->timezone)->utc() : null;
            $work->checklist = $this->checklist($input['checklist'] ?? []);
            $work->save();
            $work->issues()->sync($issues->modelKeys());
            $this->audit($work, $u, $before ? 'work_updated' : 'work_created', $before);

            return $work;
        });
    }

    public function perform(User $u, WorkOrder $m, array $input): void
    {
        DB::transaction(function () use ($u, $m, $input) {
            $d = Validator::make($input, ['revision' => 'required|integer|min:1', 'work_performed' => 'nullable|string|max:8000', 'minutes_spent' => 'nullable|integer|between:0,100000', 'observed_condition' => 'nullable|string|max:4000', 'labor_cost' => 'nullable|numeric|min:0|max:999999999999', 'external_cost' => 'nullable|numeric|min:0|max:999999999999', 'currency' => 'nullable|required_with:labor_cost,external_cost|string|regex:/^[A-Z]{3}$/', 'parts' => 'nullable|array|max:30', 'parts.*.name' => 'required|string|max:160', 'parts.*.quantity' => 'nullable|numeric|min:0.001|max:1000000', 'parts.*.asset_reference' => 'nullable|string|max:100', 'parts.*.asset_id' => 'nullable|integer', 'parts.*.cost' => 'nullable|numeric|min:0|max:999999999999'])->validate();
            $m = $this->lock($m, (int) $d['revision']);
            Gate::forUser($u)->authorize('update', $m);
            abort_if(in_array($m->state, ['closed', 'cancelled', 'verified']), 422);
            $d['parts'] ??= [];
            foreach ($d['parts'] as &$part) {
                if (filled($part['asset_reference'] ?? null)) {
                    $linked = Asset::where('property_id', $m->property_id)->available()->where(fn ($q) => $q->where('reference', mb_strtoupper(trim($part['asset_reference'])))->orWhere('uuid', trim($part['asset_reference'])))->firstOrFail();
                    $part['asset_id'] = $linked->id;
                    $part['asset_reference'] = $linked->reference;
                }
                if (isset($part['asset_id'])) {
                    Asset::where('property_id', $m->property_id)->available()->findOrFail($part['asset_id']);
                }if (isset($part['cost']) && ! filled($d['currency'] ?? null)) {
                    throw ValidationException::withMessages(['currency' => __('maintenance.currency_required')]);
                }
            }
            unset($part);
            $ctx = app(Access::class)->context($u, $m);
            if (($ctx['external'] ?? false) && collect($d['parts'])->contains(fn ($p) => isset($p['asset_id']) && $p['asset_id'] !== $m->asset_id)) {
                abort(403);
            }
            if (($ctx['external'] ?? false) && ! $ctx['costs'] && (filled($d['labor_cost'] ?? null) || filled($d['external_cost'] ?? null) || filled($d['currency'] ?? null) || collect($d['parts'])->contains(fn ($p) => isset($p['cost'])))) {
                abort(403);
            }
            $m->fill(array_intersect_key($d, array_flip(['work_performed', 'minutes_spent', 'observed_condition', 'parts', 'labor_cost', 'external_cost', 'currency'])));
            $checked = $this->checklist($input['checklist'] ?? []);
            if (array_column($checked, 'text') !== array_column($m->checklist ?? [], 'text')) {
                throw ValidationException::withMessages(['checklist' => __('maintenance.checklist_changed')]);
            }$m->checklist = $checked;
            // Any change after resolution needs a fresh resolution and verification.
            if ($m->state === 'resolved') {
                $m->state = 'in_progress';
                $m->resolved_at = null;
            }
            $known = collect($d['parts'] ?? [])->filter(fn ($p) => isset($p['cost']));
            $sum = $known->sum(fn ($p) => (float) $p['cost']);
            if ($sum > 999999999999) {
                throw ValidationException::withMessages(['parts' => __('maintenance.cost_limit')]);
            }$m->materials_cost = $known->isEmpty() ? null : $sum;
            $m->revision++;
            $m->save();
            $this->audit($m, $u, 'performed');
        });
    }

    public function transitions(User $u, WorkOrder $m): array
    {
        $manager = $this->policy()->manage($u, $m->property);

        return array_values(array_filter($manager ? config('maintenance.states') : config('maintenance.technician_states'), function ($state) use ($m) {
            if ($state === $m->state) {
                return false;
            }
            if ($state === 'verified') {
                return $m->state === 'resolved';
            }
            if ($state === 'closed') {
                return $m->state === 'verified';
            }
            if ($state === 'resolved') {
                return ! in_array($m->state, ['verified', 'closed', 'cancelled']);
            }
            if ($state === 'assigned') {
                return $m->assigned_to || filled($m->team);
            }

            return true;
        }));
    }

    public function transition(User $u, WorkOrder $m, array $input): void
    {
        DB::transaction(function () use ($u, $m, $input) {
            $d = Validator::make($input, ['revision' => 'required|integer|min:1', 'state' => ['required', Rule::in(config('maintenance.states'))], 'reason' => 'required|string|max:2000'])->validate();
            $m = $this->lock($m, (int) $d['revision']);
            Gate::forUser($u)->authorize('update', $m);
            $manager = $this->policy()->manage($u, $m->property);
            if (! $manager && (! in_array($d['state'], config('maintenance.technician_states')) || in_array($m->state, ['verified', 'closed', 'cancelled']))) {
                abort(403);
            }
            if ($d['state'] === $m->state) {
                throw ValidationException::withMessages(['state' => __('maintenance.same_state')]);
            }
            if ($d['state'] === 'assigned' && ! $m->assigned_to && ! filled($m->team)) {
                throw ValidationException::withMessages(['state' => __('maintenance.assignment_required')]);
            }
            if (in_array($d['state'], ['resolved', 'verified', 'closed']) && ! filled($m->work_performed)) {
                throw ValidationException::withMessages(['work_performed' => __('maintenance.performed_required')]);
            }
            if ($d['state'] === 'resolved' && collect($m->checklist ?? [])->contains(fn ($i) => ! $i['done'])) {
                throw ValidationException::withMessages(['checklist' => __('maintenance.checklist_required')]);
            }
            if ($d['state'] === 'verified' && $m->state !== 'resolved') {
                throw ValidationException::withMessages(['state' => __('maintenance.resolve_first')]);
            }
            if ($d['state'] === 'closed' && ($m->state !== 'verified' || ! $m->verified_at || ! $m->verified_by)) {
                throw ValidationException::withMessages(['state' => __('maintenance.verify_first')]);
            }
            if ((in_array($m->state, ['closed', 'cancelled']) || ($m->state === 'verified' && $d['state'] !== 'closed')) && in_array($d['state'], ['resolved', 'verified', 'closed'])) {
                throw ValidationException::withMessages(['state' => __('maintenance.reopen_first')]);
            }
            $before = ['state' => $m->state];
            $m->state = $d['state'];
            if ($m->state === 'resolved') {
                $m->resolved_at = now();
            } elseif ($m->state === 'verified') {
                $m->verified_at = now();
                $m->verified_by = $u->id;
            } elseif ($m->state === 'closed') {
                $m->closed_at = now();
            } elseif (! in_array($m->state, ['resolved', 'verified', 'closed'])) {
                $m->resolved_at = null;
                $m->verified_at = null;
                $m->verified_by = null;
                $m->closed_at = null;
            }
            $m->revision++;
            $m->save();
            $this->audit($m, $u, 'transition', $before, $d['reason']);
        });
    }

    public function comment(User $u, WorkOrder $m, array $input): void
    {
        DB::transaction(function () use ($u, $m, $input) {
            $d = Validator::make($input, ['body' => 'required|string|max:4000', 'private' => 'required|boolean'])->validate();
            Gate::forUser($u)->authorize('view', $m);
            $this->allowed($u, $m->property);
            $m->comments()->create(['user_id' => $u->id, 'body' => $d['body'], 'private' => $d['private'], 'created_at' => now()]);
            $this->audit($m, $u, $d['private'] ? 'private_note' : 'comment');
        });
    }

    public function plan(User $u, Property $p, array $input, ?MaintenancePlan $plan = null): MaintenancePlan
    {
        return DB::transaction(function () use ($u, $p, $input, $plan) {
            $this->allowed($u, $p, true);
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $d = $this->common($input) + Validator::make($input, ['frequency' => ['required', Rule::in(config('maintenance.frequencies'))], 'anchor_on' => 'required|date_format:Y-m-d', 'interval_count' => 'required|integer|between:1,3650', 'interval_unit' => 'required|in:day,week,month,year', 'estimated_minutes' => 'nullable|integer|between:1,100000', 'assigned_to' => 'nullable|integer', 'team' => 'nullable|string|max:120', 'notify_assignee' => 'required|boolean', 'revision' => $plan ? 'required|integer|min:1' : 'nullable|integer'])->validate();
            $before = null;
            if ($plan) {
                $plan = $this->lock($plan, (int) $d['revision']);
                Gate::forUser($u)->authorize('update', $plan);
                $before = ['state' => $plan->state];
                $plan->revision++;
            } else {
                $plan = new MaintenancePlan(['organization_id' => $p->organization_id, 'property_id' => $p->id, 'created_by' => $u->id, 'state' => 'active']);
            }
            $changed = ! $plan->exists || $plan->anchor_on?->format('Y-m-d') !== $d['anchor_on'] || $plan->frequency !== $d['frequency'] || $plan->interval_count != (int) $d['interval_count'] || $plan->interval_unit !== $d['interval_unit'];
            $plan->fill(array_intersect_key($d, array_flip(['title', 'description', 'category', 'frequency', 'anchor_on', 'interval_count', 'interval_unit', 'estimated_minutes', 'team', 'notify_assignee'])) + $this->location($p, $d));
            $plan->managed_by = $u->id;
            $plan->assigned_to = $this->assignee($u, $p, $d['assigned_to'] ?? null);
            $plan->checklist = $this->checklist($input['checklist'] ?? []);
            if ($changed) {
                $plan->occurrence = 0;
                $plan->next_on = $d['frequency'] === 'usage' ? null : $d['anchor_on'];
            }
            $plan->save();
            $this->audit($plan, $u, 'plan_saved', $before);

            return $plan;
        });
    }

    public function suspend(User $u, MaintenancePlan $m, array $input): void
    {
        DB::transaction(function () use ($u, $m, $input) {
            $d = Validator::make($input, ['revision' => 'required|integer|min:1', 'state' => 'required|in:active,suspended', 'reason' => 'required|string|max:2000'])->validate();
            $m = $this->lock($m, (int) $d['revision']);
            $this->allowed($u, $m->property, true);
            Gate::forUser($u)->authorize('view', $m);
            $before = ['state' => $m->state];
            $m->state = $d['state'];
            $m->suspension_reason = $d['reason'];
            $m->revision++;
            $m->save();
            $this->audit($m, $u, 'plan_state', $before, $d['reason']);
        });
    }

    public function repeats(WorkOrder|MaintenanceIssue $m)
    {
        $q = WorkOrder::where('property_id', $m->property_id)->available()->where('category', $m->category)->whereIn('state', ['resolved', 'verified', 'closed'])->where('resolved_at', '>=', now()->subDays(app(Settings::class)->all()['repeat_days']));

        return $m->asset_id ? $q->where('asset_id', $m->asset_id) : $q->whereRaw('1=0');
    }

    public function generate(MaintenancePlan $plan): int
    {
        return DB::transaction(function () use ($plan) {
            Property::whereKey($plan->property_id)->lockForUpdate()->firstOrFail();
            $p = MaintenancePlan::lockForUpdate()->findOrFail($plan->id);
            $managerId = $p->managed_by ?? $p->created_by;
            $u = $managerId ? User::find($managerId) : null;
            if ($p->state !== 'active' || ! $p->next_on || ! $u || ! $this->policy()->manage($u, $p->property) || ($p->building_id && ! $p->building()->exists()) || ($p->asset_id && $p->asset->archived_at)) {
                return 0;
            }
            $today = now($p->property->timezone)->toDateString();
            $n = 0;
            for ($i = 0; $i < 20 && $p->next_on && $p->next_on->toDateString() <= $today; $i++) {
                $date = $p->next_on->toDateString();
                $existing = WorkOrder::where('plan_id', $p->id)->whereDate('scheduled_for', $date)->exists();
                if (! $existing) {
                    $assigned = $p->assigned_to && $this->policy()->report(User::find($p->assigned_to), $p->property) ? $p->assigned_to : null;
                    $w = WorkOrder::create(['organization_id' => $p->organization_id, 'property_id' => $p->property_id, 'building_id' => $p->building_id, 'space_id' => $p->space_id, 'asset_id' => $p->asset_id, 'created_by' => $u->id, 'title' => $p->title, 'description' => $p->description, 'category' => $p->category, 'state' => $assigned || $p->team ? 'assigned' : 'reported', 'priority' => 'normal', 'assigned_to' => $assigned, 'team' => $p->team, 'checklist' => array_map(fn ($i) => ['text' => $i['text'], 'done' => false], $p->checklist ?? []), 'plan_id' => $p->id, 'scheduled_for' => $date, 'due_at' => CarbonImmutable::parse($date, $p->property->timezone)->endOfDay()->utc()]);
                    $this->audit($w, null, 'scheduled', null, $p->reference);
                    $n++;
                    if ($p->notify_assignee && $assigned) {
                        NotifyMaintenance::dispatch($w->id)->onConnection('database')->onQueue('maintenance')->afterCommit();
                    }
                }
                $p->occurrence++;
                [$count,$unit] = match ($p->frequency) {
                    'daily' => [1, 'day'],'weekly' => [1, 'week'],'monthly' => [1, 'month'],'quarterly' => [3, 'month'],'annually' => [1, 'year'],default => [$p->interval_count, $p->interval_unit]
                };
                $steps = $count * $p->occurrence;
                $p->next_on = match ($unit) {
                    'month' => $p->anchor_on->addMonthsNoOverflow($steps),'year' => $p->anchor_on->addYearsNoOverflow($steps),'week' => $p->anchor_on->addWeeks($steps),default => $p->anchor_on->addDays($steps)
                };
            }if ($i > 0) {
                $p->revision++;
            }$p->save();

            return $n;
        });
    }
}
