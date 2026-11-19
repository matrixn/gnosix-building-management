<?php

namespace App\Operations;

use App\Models\Asset;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Models\WorkspaceGroup;
use App\Support\CapabilityService;
use App\Support\PropertyPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class Scope
{
    public ?WorkspaceGroup $portfolio = null;

    public ?Property $property = null;

    public ?Building $building = null;

    public ?Floor $floor = null;

    public ?Space $space = null;

    public ?Asset $asset = null;

    public readonly array $capabilities;

    private readonly Builder $visible;

    private readonly Builder $editable;

    public function __construct(public readonly User $user, public readonly Organization $organization, public readonly array $filters)
    {
        abort_if($user->isArchived() || ! $user->hasVerifiedEmail(), 403);
        Gate::forUser($user)->authorize('view', $organization);
        $capabilities = app(CapabilityService::class);
        abort_unless($capabilities->allows($user, $organization, 'operations.view'), 403);
        $allows = fn ($key) => $capabilities->allows($user, $organization, $key);
        $assets = $allows('assets.view');
        $rooms = $allows('hospitality.rooms');
        $this->capabilities = [
            'maintenance' => $allows('hospitality.maintenance'),
            'assets' => $assets,
            'rooms' => $rooms,
            'evidence' => $allows('evidence.upload') || $allows('evidence.review'),
            'dna' => $assets && $rooms && $allows('dna.view'),
            'history' => $allows('time.view'),
        ];
        $permissions = app(PropertyPermissions::class);
        $this->visible = $permissions->visible($user, $organization);
        $this->editable = $permissions->visible($user, $organization, true);

        if (! empty($filters['group'])) {
            $this->portfolio = $organization->groups()->whereHas('properties', fn ($p) => $p->whereIn('properties.id', (clone $this->visible)->select('properties.id')))
                ->findOrFail($filters['group']);
        }
        if (! empty($filters['property'])) {
            $this->property = $this->properties()->findOrFail($filters['property']);
        }
        if (! empty($filters['building'])) {
            $this->building = $this->buildings()->findOrFail($filters['building']);
        }
        if (! empty($filters['floor']) || ! empty($filters['space'])) {
            abort_unless($rooms && $this->building, 404);
        }
        if (! empty($filters['floor'])) {
            $this->floor = $this->building->floors()->findOrFail($filters['floor']);
        }
        if (! empty($filters['space'])) {
            $this->space = $this->building->spaces()->when($this->floor, fn ($q) => $q->where('floor_id', $this->floor->id))->findOrFail($filters['space']);
        }
        if (! empty($filters['asset'])) {
            abort_unless($assets, 404);
            $this->asset = $this->assets()->findOrFail($filters['asset']);
        }
    }

    public function properties(bool $write = false): Builder
    {
        return (clone ($write ? $this->editable : $this->visible))
            ->when($this->portfolio, fn ($q) => $q->whereHas('groups', fn ($group) => $group->whereKey($this->portfolio->id)))
            ->when($this->property, fn ($q) => $q->whereKey($this->property->id))
            ->when($this->building && ! $this->property, fn ($q) => $q->whereKey($this->building->property_id))
            ->when($this->asset && ! $this->property, fn ($q) => $q->whereKey($this->asset->property_id));
    }

    public function buildings(): Builder
    {
        return Building::whereIn('property_id', $this->properties()->select('properties.id'))->where('state', '!=', 'archived')
            ->when($this->building, fn ($q) => $q->whereKey($this->building->id));
    }

    public function matchSpace(Builder $query): Builder
    {
        return $query->when($this->building, fn ($q) => $q->where('building_id', $this->building->id))
            ->when($this->floor, fn ($q) => $q->where('floor_id', $this->floor->id))
            ->when($this->space, fn ($q) => $q->whereKey($this->space->id));
    }

    public function assets(): Builder
    {
        return Asset::where('organization_id', $this->organization->id)->whereIn('property_id', $this->properties()->select('properties.id'))
            ->whereNull('archived_at')->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building', fn ($b) => $b->where('state', '!=', 'archived')))
            ->when($this->asset, fn ($q) => $q->whereKey($this->asset->id))
            ->when($this->building, fn ($q) => $q->where(function ($within) {
                $within->where(fn ($loc) => $loc->where('building_id', $this->building->id)
                    ->when($this->floor, fn ($q) => $q->where('floor_id', $this->floor->id))
                    ->when($this->space, fn ($q) => $q->where('space_id', $this->space->id)))
                    ->orWhereHas('servedSpaces', fn ($space) => $this->matchSpace($space));
            }));
    }

    public function records(string $class): Builder
    {
        return $class::where('organization_id', $this->organization->id)->whereIn('property_id', $this->properties()->select('properties.id'))
            ->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building', fn ($b) => $b->where('state', '!=', 'archived')))
            ->when($this->asset, fn ($q) => $q->where('asset_id', $this->asset->id))
            ->when($this->building, fn ($q) => $q->where(function ($within) {
                $within->where(fn ($loc) => $loc->where('building_id', $this->building->id)
                    ->when($this->floor || $this->space, fn ($q) => $q->whereHas('space', fn ($s) => $this->matchSpace($s))))
                    ->when($this->capabilities['assets'], fn ($q) => $q->orWhereIn('asset_id', $this->assets()->select('assets.id')));
            }));
    }

    public function fingerprint(): string
    {
        $hash = hash_init('sha256');
        hash_update($hash, json_encode([$this->filters, $this->capabilities], JSON_THROW_ON_ERROR));
        foreach ([false, true] as $write) {
            hash_update($hash, $write ? '|edit|' : '|view|');
            foreach ($this->properties($write)->select('properties.id')->lazyById(500, 'properties.id', 'id') as $property) {
                hash_update($hash, $property->id.',');
            }
        }

        hash_update($hash, '|buildings|');
        foreach ($this->buildings()->select('buildings.id')->lazyById(500, 'buildings.id', 'id') as $building) {
            hash_update($hash, $building->id.',');
        }

        return hash_final($hash);
    }
}
