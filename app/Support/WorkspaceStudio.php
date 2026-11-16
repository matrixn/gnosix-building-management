<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WorkspaceDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkspaceStudio
{
    public function __construct(private WorkspaceQuestions $questions) {}

    public function authorize(User $user, Organization $organization): void
    {
        Gate::forUser($user)->authorize('update', $organization);
        abort_unless(app(CapabilityService::class)->allows($user, $organization, 'hospitality.workspace') && app(CapabilityService::class)->allows($user, $organization, 'hospitality.buildings'), 403);
    }

    public function resume(User $user, Organization $organization): WorkspaceDraft
    {
        $this->authorize($user, $organization);
        return WorkspaceDraft::firstOrCreate(['user_id' => $user->id, 'organization_id' => $organization->id], ['state' => [], 'step' => 'vision', 'guided' => (bool) (UserPreference::where('user_id', $user->id)->value('guided_setup') ?? true), 'version' => 1]);
    }

    public function save(User $user, Organization $organization, array $data, bool $commit = false): WorkspaceDraft
    {
        return DB::transaction(function () use ($user, $organization, $data, $commit) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $this->authorize($user, $organization);
            $draft = WorkspaceDraft::where('user_id', $user->id)->where('organization_id', $organization->id)->lockForUpdate()->firstOrFail();
            if ($commit && $draft->completed_at) return $draft;
            if ($draft->completed_at || $draft->version !== (int) $data['version']) throw ValidationException::withMessages(['draft' => __('workspace.draft_conflict')]);
            $state = array_replace($draft->state, $data['answers']);
            $draft->fill(['state' => $state, 'guided' => (bool) $data['guided'], 'step' => $data['step'], 'version' => $draft->version + 1]);
            if ($commit) {
                $answers = $this->questions->complete($state);
                $plan = $this->questions->plan($answers);
                if ($plan['spaces'] && ! app(CapabilityService::class)->allows($user, $organization, 'hospitality.rooms')) throw ValidationException::withMessages(['answers.room_count' => __('studio.spaces_disabled')]);
                $locale = app()->getLocale();
                try {
                    app()->setLocale($organization->locale);
                    $draft->result = BuildingAudit::without(fn () => $this->build($organization, $answers));
                    // Record the accepted initial structure once per building, not once per room.
                    foreach (\App\Models\Building::whereIn('property_id', $draft->result['property_ids'])->get() as $building) {
                        BuildingAudit::record($building, 'declared', 'declaration', $building->uuid, null, app(BuildingDeclarationStudio::class)->snapshot($building), 'manual', false);
                    }
                } finally {
                    app()->setLocale($locale);
                }
                $draft->completed_at = now();
            }
            $draft->save();
            UserPreference::updateOrCreate(['user_id' => $user->id], ['guided_setup' => (bool) $data['guided']]);
            return $draft;
        });
    }

    public function restart(User $user, Organization $organization): void
    {
        DB::transaction(function () use ($user, $organization) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $this->authorize($user, $organization);
            $draft = WorkspaceDraft::where('user_id', $user->id)->where('organization_id', $organization->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->completed_at, 409);
            $draft->update(['state' => [], 'result' => null, 'completed_at' => null, 'step' => 'vision', 'version' => $draft->version + 1]);
        });
    }

    private function build(Organization $organization, array $a): array
    {
        $group = $a['group_name'] ? $organization->groups()->create(['name' => $a['group_name'], 'kind' => 'portfolio']) : null;
        $entity = $a['legal_entity_name'] ? $organization->legalEntities()->create(['name' => $a['legal_entity_name'], 'country' => $organization->country]) : null;
        $ids = [];
        for ($p = 1; $p <= $a['property_count']; $p++) {
            $property = $organization->properties()->create(['name' => $a['property_name'].($a['property_count'] > 1 ? ' '.$p : ''), 'property_type' => $a['property_type'], 'legal_entity_id' => $entity?->id, 'country' => $organization->country, 'timezone' => $organization->timezone, 'amenities' => $a['amenities']]);
            if ($group) $property->groups()->attach($group->id);
            $ids[] = $property->id; $slots = [];
            for ($b = 1; $b <= $a['building_count']; $b++) {
                $building = $property->buildings()->create(['name' => __('studio.building').' '.$b]);
                if (! $a['floor_count']) $slots[] = ['building_id' => $building->id, 'floor_id' => null];
                for ($f = 0; $f < $a['floor_count']; $f++) {
                    $floor = $building->floors()->create(['name' => __('studio.floor').' '.$f, 'position' => $f]);
                    $slots[] = ['building_id' => $building->id, 'floor_id' => $floor->id];
                }
            }
            $slots = $slots ?: [['building_id' => null, 'floor_id' => null]];
            for ($r = 0; $r < $a['room_count']; $r++) {
                $number = $a['numbering_prefix'].($a['numbering_start'] + $r);
                $property->spaces()->create($slots[$r % count($slots)] + ['name' => __('studio.type_'.$a['accommodation_type']).' '.$number, 'space_type' => $a['accommodation_type'], 'number' => $number]);
            }
            for ($d = 0; $d < $a['dormitory_count']; $d++) $property->spaces()->create($slots[$d % count($slots)] + ['name' => __('studio.type_dormitory').' '.($d + 1), 'space_type' => 'dormitory', 'bed_count' => $a['beds_per_dorm'], 'capacity' => $a['beds_per_dorm']]);
            foreach ($a['shared_facilities'] as $facility) $property->spaces()->create(['name' => __('studio.option_'.$facility), 'space_type' => 'common']);
        }
        return ['property_ids' => $ids, 'plan' => $this->questions->plan($a)];
    }
}
