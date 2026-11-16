<?php

namespace App\Support;

use App\Models\Building;
use App\Models\BuildingDeclarationDraft;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuildingDeclarationStudio
{
    public function __construct(private BuildingDeclarationSchema $schema) {}

    public function authorize(User $u, Property $p, bool $write = false): void
    {
        Gate::forUser($u)->authorize($write ? 'update' : 'view', $p);
    }

    public function hasSpaces(array $state): bool
    {
        return count($state['unplaced_spaces']) > 0 || collect($state['levels'])->contains(fn ($l) => count($l['spaces']) > 0);
    }

    public function rooms(User $u, Property $p, array $state): void
    {
        if ($this->hasSpaces($state) || $state['units']) {
            abort_unless(app(CapabilityService::class)->allows($u, $p->organization, 'hospitality.rooms'), 403);
        }
    }

    public function snapshot(Building $b): array
    {
        $identity = [];
        foreach (config('building-studio.identity_fields') as $f) {
            $identity[$f] = $b->{$f};
        }
        $levels = $b->floors()->orderBy('position')->orderBy('id')->get();
        $spaces = $b->spaces()->with('beds')->orderBy('id')->get();
        $byFloor = $spaces->groupBy('floor_id');
        $spaceRow = fn ($s) => ['key' => $s->uuid, 'name' => $s->name, 'space_type' => $s->space_type, 'number' => $s->number, 'bed_count' => $s->bed_count, 'capacity' => $s->capacity, 'area_m2' => $s->area_m2, 'beds' => $s->beds->map(fn ($bed) => ['key' => $bed->uuid, 'label' => $bed->label])->all()];

        return ['identity' => $identity, 'levels' => $levels->map(fn ($l) => ['key' => $l->uuid, 'name' => $l->name, 'kind' => $l->kind, 'spaces' => $byFloor->get($l->id, collect())->map($spaceRow)->values()->all()])->all(), 'unplaced_spaces' => $byFloor->get('', collect())->map($spaceRow)->values()->all(), 'units' => $b->accommodationUnits()->with(['spaces', 'bed'])->orderBy('id')->get()->map(fn ($a) => ['key' => $a->uuid, 'name' => $a->name, 'unit_type' => $a->unit_type, 'space_keys' => $a->spaces->pluck('uuid')->all(), 'bed_key' => $a->bed?->uuid])->all()];
    }

    public function resume(User $u, Property $p, ?Building $b = null): BuildingDeclarationDraft
    {
        $this->authorize($u, $p, true);
        if ($b) {
            abort_unless($b->property_id === $p->id, 404);
        }

        return DB::transaction(function () use ($u, $p, $b) {
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $this->authorize($u, $p, true);
            $draft = BuildingDeclarationDraft::where('user_id', $u->id)->where('property_id', $p->id)->where('building_id', $b?->id)->whereNull('completed_at')->when($b, fn ($q) => $q->where('base_version', $b->declaration_version))->orderByDesc('created_at')->first();
            if ($draft) {
                $this->rooms($u, $p, $draft->state);

                return $draft;
            }
            $state = $b ? $this->snapshot($b) : $this->schema->empty();
            $this->rooms($u, $p, $state);

            return BuildingDeclarationDraft::create(['id' => (string) Str::uuid(), 'property_id' => $p->id, 'user_id' => $u->id, 'building_id' => $b?->id, 'base_version' => $b?->declaration_version, 'version' => 1, 'state' => $state]);
        });
    }

    public function save(User $u, Property $p, string $id, int $version, array $state, bool $commit = false, string $source = 'manual', string $stage = 'identity'): BuildingDeclarationDraft
    {
        return DB::transaction(function () use ($u, $p, $id, $version, $state, $commit, $source, $stage) {
            Property::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $this->authorize($u, $p, true);
            $draft = BuildingDeclarationDraft::where('property_id', $p->id)->where('user_id', $u->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($draft->completed_at && $commit) {
                $this->rooms($u, $p, $draft->state);

                return $draft;
            }
            if ($draft->completed_at || $draft->version !== $version) {
                throw ValidationException::withMessages(['version' => __('building.draft_conflict')]);
            }
            $state = $this->schema->validate($state, $commit);
            $this->rooms($u, $p, $state);
            $b = $draft->building_id ? Building::where('property_id', $p->id)->whereKey($draft->building_id)->lockForUpdate()->firstOrFail() : null;
            if ($b && $b->declaration_version !== $draft->base_version) {
                throw ValidationException::withMessages(['building' => __('building.building_conflict')]);
            }
            $this->checkKeys($state, $b);
            if ($commit) {
                $before = $b ? $this->snapshot($b) : null;
                $created = ! $b;
                $b = BuildingAudit::without(function () use ($b, $state, $p) {
                    $identity = $state['identity'];
                    if (! filled($identity['reference'])) {
                        unset($identity['reference']);
                    }
                    $duplicate = isset($identity['reference']) && Building::where('reference', $identity['reference'])->when($b, fn ($q) => $q->where('id', '!=', $b->id))->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['reference' => __('building.reference_taken')]);
                    }
                    if ($b) {
                        $b->update($identity);
                    } else {
                        $b = $p->buildings()->create($identity);
                    }
                    $b->refresh();
                    $this->persist($b, $state);

                    return $b;
                });
                $after = $this->snapshot($b);
                BuildingAudit::record($b, $created ? 'declared' : 'revised', 'declaration', $b->uuid, $before, $after, $source, ! $created);
                $draft->building_id = $b->id;
                $draft->completed_at = now();
                $state = $after;
            }
            $draft->state = $state;
            $draft->stage = $stage;
            $draft->source_kind = $source;
            $draft->version++;
            $draft->save();

            return $draft;
        });
    }

    private function checkKeys(array $s, ?Building $b): void
    {
        $spaces = collect($s['levels'])->flatMap(fn ($l) => $l['spaces'])->concat($s['unplaced_spaces']);
        $sets = ['floors' => collect($s['levels'])->pluck('key')->all(), 'spaces' => $spaces->pluck('key')->all(), 'space_beds' => $spaces->flatMap(fn ($s) => $s['beds'])->pluck('key')->all(), 'accommodation_units' => array_column($s['units'], 'key')];
        foreach ($sets as $table => $keys) {
            foreach (array_chunk($keys, 500) as $chunk) {
                $q = DB::table($table)->whereIn($table.'.uuid', $chunk);
                if ($table === 'space_beds') {
                    $q->join('spaces', 'spaces.id', '=', 'space_beds.space_id')->select('spaces.building_id');
                } else {
                    $q->select('building_id');
                }
                foreach ($q->get() as $row) {
                    if (! $b || (int) $row->building_id !== $b->id) {
                        throw ValidationException::withMessages(['structure' => __('building.foreign_key')]);
                    }
                }
            }
        }
    }

    private function upsert(string $table, array $rows, array $columns): void
    {
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table($table)->upsert($chunk, ['uuid'], $columns);
        }
    }

    private function persist(Building $b, array $s): void
    {
        $now = now();
        $levelRows = [];
        foreach ($s['levels'] as $i => $l) {
            $levelRows[] = ['uuid' => $l['key'], 'building_id' => $b->id, 'name' => $l['name'], 'position' => $i, 'kind' => $l['kind'], 'archived_at' => null, 'created_at' => $now, 'updated_at' => $now];
        }
        $this->upsert('floors', $levelRows, ['name', 'position', 'kind', 'archived_at', 'updated_at']);
        $floorIds = DB::table('floors')->where('building_id', $b->id)->pluck('id', 'uuid');
        $spaceRows = [];
        $bedRows = [];
        $collectSpaces = function ($spaces, $floorId) use (&$spaceRows, $b, $now) {
            foreach ($spaces as $v) {
                $spaceRows[] = ['uuid' => $v['key'], 'property_id' => $b->property_id, 'building_id' => $b->id, 'floor_id' => $floorId, 'name' => $v['name'], 'space_type' => $v['space_type'], 'number' => $v['number'], 'bed_count' => $v['bed_count'], 'capacity' => $v['capacity'], 'area_m2' => $v['area_m2'], 'archived_at' => null, 'created_at' => $now, 'updated_at' => $now];
            }
        };
        foreach ($s['levels'] as $l) {
            $collectSpaces($l['spaces'], $floorIds[$l['key']]);
        }$collectSpaces($s['unplaced_spaces'], null);
        $this->upsert('spaces', $spaceRows, ['floor_id', 'name', 'space_type', 'number', 'bed_count', 'capacity', 'area_m2', 'archived_at', 'updated_at']);
        $spaceIds = DB::table('spaces')->where('building_id', $b->id)->pluck('id', 'uuid');
        $all = collect($s['levels'])->flatMap(fn ($l) => $l['spaces'])->concat($s['unplaced_spaces']);
        foreach ($all as $space) {
            foreach ($space['beds'] as $bed) {
                $bedRows[] = ['uuid' => $bed['key'], 'space_id' => $spaceIds[$space['key']], 'label' => $bed['label'], 'archived_at' => null, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->upsert('space_beds', $bedRows, ['space_id', 'label', 'archived_at', 'updated_at']);
        $bedIds = DB::table('space_beds')->whereIn('space_id', $spaceIds->values())->pluck('id', 'uuid');
        $unitRows = [];
        foreach ($s['units'] as $u) {
            $unitRows[] = ['uuid' => $u['key'], 'building_id' => $b->id, 'name' => $u['name'], 'unit_type' => $u['unit_type'], 'bed_id' => $u['bed_key'] ? $bedIds[$u['bed_key']] : null, 'archived_at' => null, 'created_at' => $now, 'updated_at' => $now];
        }
        $this->upsert('accommodation_units', $unitRows, ['name', 'unit_type', 'bed_id', 'archived_at', 'updated_at']);
        $unitIds = DB::table('accommodation_units')->where('building_id', $b->id)->pluck('id', 'uuid');
        $updatedUnitIds = collect($s['units'])->map(fn ($u) => $unitIds[$u['key']])->all();
        foreach (array_chunk($updatedUnitIds, 500) as $ids) {
            DB::table('accommodation_unit_space')->whereIn('accommodation_unit_id', $ids)->delete();
        }
        $links = [];
        foreach ($s['units'] as $u) {
            foreach ($u['space_keys'] as $key) {
                $links[] = ['accommodation_unit_id' => $unitIds[$u['key']], 'space_id' => $spaceIds[$key]];
            }
        }
        foreach (array_chunk($links, 500) as $rows) {
            DB::table('accommodation_unit_space')->insert($rows);
        }
        DB::table('floors')->where('building_id', $b->id)->whereNotIn('uuid', array_column($levelRows, 'uuid'))->whereNull('archived_at')->update(['archived_at' => $now]);
        DB::table('spaces')->where('building_id', $b->id)->whereNotIn('uuid', array_column($spaceRows, 'uuid'))->whereNull('archived_at')->update(['archived_at' => $now]);
        DB::table('space_beds')->whereIn('space_id',$spaceIds->values())->whereNotIn('uuid',array_column($bedRows,'uuid'))->whereNull('archived_at')->update(['archived_at' => $now]);
        DB::table('accommodation_units')->where('building_id',$b->id)->whereNotIn('uuid',array_column($unitRows,'uuid'))->whereNull('archived_at')->update(['archived_at' => $now]);
    }
}
