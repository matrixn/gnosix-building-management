<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Space;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropertyController extends Controller
{
    private function authorizeList(Request $request, Organization $organization, string $capability = 'hospitality.buildings'): void
    {
        Gate::forUser($request->user('web'))->authorize('view', $organization);
        abort_unless(app(CapabilityService::class)->allows($request->user('web'), $organization, $capability), 403);
    }

    private function authorizeProperty(Request $request, Organization $organization, Property $property, bool $write = false, bool $spaces = false): void
    {
        abort_unless($property->organization_id === $organization->id, 404);
        Gate::forUser($request->user('web'))->authorize($write ? 'update' : 'view', $property);
        if ($spaces) abort_unless(app(CapabilityService::class)->allows($request->user('web'), $organization, 'hospitality.rooms'), 403);
    }

    public function index(Request $request, Organization $organization, PropertyPermissions $permissions)
    {
        $this->authorizeList($request, $organization);
        $properties = $permissions->visible($request->user('web'), $organization)->withCount('buildings')->orderBy('name')->paginate(24);
        return view('properties.index', compact('organization', 'properties'));
    }

    public function store(Request $request, Organization $organization)
    {
        $this->authorizeList($request, $organization);
        Gate::forUser($request->user('web'))->authorize('update', $organization);
        $data = $this->propertyData($request, $organization);
        $property = DB::transaction(function () use ($organization, $data) {
            $property = $organization->properties()->create(collect($data)->except('group_ids')->all());
            $property->groups()->sync($data['group_ids'] ?? []);
            return $property;
        });
        return redirect()->route('app.properties.show', [$organization, $property])->with('status', __('studio.saved'));
    }

    public function show(Request $request, Organization $organization, Property $property)
    {
        $this->authorizeProperty($request, $organization, $property);
        $canSpaces = app(CapabilityService::class)->allows($request->user('web'), $organization, 'hospitality.rooms');
        $property->load(['buildings' => fn ($q) => $q->with('property')->withCount(['floors', 'spaces']), 'groups', 'legalEntity']);
        return view('properties.show', compact('organization', 'property', 'canSpaces'));
    }

    public function update(Request $request, Organization $organization, Property $property)
    {
        $this->authorizeProperty($request, $organization, $property, true);
        $data = $this->propertyData($request, $organization);
        DB::transaction(function () use ($property, $data, $request) {
            $property = Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user('web'))->authorize('update', $property);
            $property->update(collect($data)->except('group_ids')->all());
            if (array_key_exists('group_ids', $data)) $property->groups()->sync($data['group_ids'] ?? []);
        });
        return back()->with('status', __('studio.saved'));
    }

    private function propertyData(Request $request, Organization $organization): array
    {
        if (is_string($request->input('country'))) $request->merge(['country' => strtoupper($request->input('country'))]);
        if ($request->has('group_ids') && $request->input('group_ids') === null) $request->merge(['group_ids' => []]);
        $rules = ['name' => ['required', 'string', 'max:150'], 'property_type' => ['required', Rule::in(config('workspace.property_types'))], 'address' => ['nullable', 'string', 'max:500'], 'country' => array_merge(['sometimes'], \App\Support\WorkspacePreferences::countryRules()), 'timezone' => array_merge(['sometimes'], \App\Support\WorkspacePreferences::timezoneRules()), 'amenities' => ['sometimes', 'array', 'max:20'], 'amenities.*' => [Rule::in(config('workspace.amenities'))]];
        $manager = Gate::forUser($request->user('web'))->allows('update', $organization);
        if (! $manager && ($request->has('group_ids') || $request->has('legal_entity_id'))) abort(403);
        if ($manager) $rules += ['legal_entity_id' => ['nullable', 'integer', Rule::exists('legal_entities', 'id')->where('organization_id', $organization->id)], 'group_ids' => ['sometimes', 'array', 'max:100'], 'group_ids.*' => ['integer', 'distinct', Rule::exists('workspace_groups', 'id')->where('organization_id', $organization->id)]];
        $data = $request->validate($rules);
        if ($request->routeIs('app.properties.store')) $data += ['country' => $organization->country, 'timezone' => $organization->timezone];
        return $data;
    }

    public function building(Request $request, Organization $organization, Property $property, ?Building $building = null)
    {
        $this->authorizeProperty($request, $organization, $property, true);
        if ($building) { abort_unless($building->property_id === $property->id, 404); abort_if($building->state === 'archived', 403); }
        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);
        DB::transaction(fn () => $building ? $building->update($data) : $property->buildings()->create($data));
        return back()->with('status', __('studio.saved'));
    }

    public function floor(Request $request, Organization $organization, Property $property, Building $building, ?Floor $floor = null)
    {
        $this->authorizeProperty($request, $organization, $property, true);
        abort_unless($building->property_id === $property->id, 404);
        abort_if($building->state === 'archived', 403);
        if ($floor) abort_unless($floor->building_id === $building->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'position' => ['required', 'integer', 'min:-20', 'max:300']]);
        DB::transaction(fn () => $floor ? $floor->update($data) : $building->floors()->create($data));
        return back()->with('status', __('studio.saved'));
    }

    public function spaces(Request $request, Organization $organization, PropertyPermissions $permissions)
    {
        $this->authorizeList($request, $organization, 'hospitality.rooms');
        $this->authorizeList($request, $organization);
        $filters = $request->validate(['q' => 'nullable|string|max:150', 'property' => 'nullable|integer', 'state' => ['nullable', Rule::in(['draft', 'active', 'archived'])]]);
        $properties = $permissions->visible($request->user('web'), $organization)->orderBy('name')->get(['id', 'organization_id', 'name']);
        $visible = $permissions->visible($request->user('web'), $organization)->select('id');
        $buildings = Building::whereIn('property_id', $visible)->with('property')->withCount(['floors', 'spaces'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('reference', 'like', '%'.$term.'%')))
            ->when($filters['property'] ?? null, fn ($q, $id) => $q->where('property_id', $id))
            ->when($filters['state'] ?? null, fn ($q, $state) => $q->where('state', $state))
            ->orderBy('name')->paginate(24, ['*'], 'buildings_page')->withQueryString();
        $unassigned = Space::whereIn('property_id', $visible)->whereNull('building_id')->with('property')->orderBy('name')->paginate(20, ['*'], 'unassigned_page')->withQueryString();
        $targets = $unassigned->isEmpty() ? collect() : Building::whereIn('property_id', $unassigned->pluck('property_id'))->where('state', '!=', 'archived')->orderBy('name')->get(['id', 'property_id', 'name']);
        return view('properties.spaces', compact('organization', 'properties', 'buildings', 'unassigned', 'targets'));
    }

    public function editSpace(Request $request, Organization $organization, Property $property, Space $space)
    {
        $this->authorizeProperty($request, $organization, $property, true, true);
        abort_unless($space->property_id === $property->id, 404);
        if (! $space->building_id) return redirect(route('app.spaces.index', $organization).'#unassigned-'.$space->uuid);
        return redirect()->route('app.declarations.edit', [$organization, $property, $space->building->uuid, 'space' => $space->uuid]);
    }

    public function trash(Request $request, Organization $organization, PropertyPermissions $permissions)
    {
        $this->authorizeList($request, $organization, 'hospitality.rooms');
        $this->authorizeList($request, $organization);
        $buildings = Building::onlyTrashed()->whereIn('property_id', $permissions->visible($request->user('web'), $organization)->select('id'))
            ->with('property')->latest('deleted_at')->orderByDesc('id')->paginate(24);

        return view('buildings.trash', compact('organization', 'buildings'));
    }

    public function assignStudio(Request $request, Organization $organization, Property $property, Space $space)
    {
        $this->authorizeProperty($request, $organization, $property, true, true);
        abort_unless($space->property_id === $property->id, 404);
        $data = $request->validate(['building_id' => ['required', 'integer', Rule::exists('buildings', 'id')->where('property_id', $property->id)->whereNot('state', 'archived')]]);
        $building = DB::transaction(function () use ($request, $property, $space, $data) {
            Property::whereKey($property->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user('web'))->authorize('update', $property);
            $space = Space::where('property_id', $property->id)->lockForUpdate()->findOrFail($space->id);
            abort_unless($space->building_id === null, 409);
            $building = $property->buildings()->where('state', '!=', 'archived')->lockForUpdate()->findOrFail($data['building_id']);
            $space->update(['building_id' => $building->id, 'floor_id' => null]);
            return $building;
        });
        return redirect()->route('app.declarations.edit', [$organization, $property, $building->uuid, 'space' => $space->uuid]);
    }

    public function space(Request $request, Organization $organization, Property $property, ?Space $space = null)
    {
        $this->authorizeProperty($request, $organization, $property, true, true);
        if ($space) abort_unless($space->property_id === $property->id, 404);
        if ($space) {
            foreach (['building_id', 'floor_id'] as $key) if (! $request->exists($key)) $request->merge([$key => $space->{$key}]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'space_type' => ['required', Rule::in(config('workspace.space_types'))], 'number' => ['nullable', 'string', 'max:40'], 'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')->where('property_id', $property->id)->whereNot('state', 'archived')], 'floor_id' => ['nullable', 'integer', Rule::exists('floors', 'id')->whereNull('archived_at')->whereIn('building_id', $property->buildings()->select('id'))], 'bed_count' => ['nullable', 'integer', 'min:0', 'max:1000'], 'capacity' => ['nullable', 'integer', 'min:0', 'max:1000'], 'area' => ['nullable', 'numeric', 'min:0', 'max:100000000']]);
        if ($space && $space->building_id && $space->building?->state === 'archived') abort(403);
        if ($space && (int)($data['building_id'] ?? 0) !== (int)$space->building_id && (\Illuminate\Support\Facades\DB::table('accommodation_unit_space')->where('space_id', $space->id)->exists() || $space->beds()->exists())) throw ValidationException::withMessages(['building_id' => __('building.linked_space')]);
        if ($space && $space->beds()->exists() && array_key_exists('bed_count', $data) && (int)$data['bed_count'] !== $space->beds()->count()) throw ValidationException::withMessages(['bed_count' => __('building.bed_mismatch')]);
        if (! empty($data['floor_id']) && (int) Floor::findOrFail($data['floor_id'])->building_id !== (int) ($data['building_id'] ?? 0)) throw ValidationException::withMessages(['floor_id' => __('studio.floor_mismatch')]);
        if (! $space || $request->exists('area')) $data['area_m2'] = isset($data['area']) ? round((float) $data['area'] * ($organization->unit_system === 'imperial' ? 0.09290304 : 1), 3) : null;
        unset($data['area']);
        DB::transaction(fn () => $space ? $space->update($data) : $property->spaces()->create($data));
        return redirect()->route('app.properties.show', [$organization, $property])->with('status', __('studio.saved'));
    }
}
