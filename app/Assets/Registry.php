<?php

namespace App\Assets;

use App\Evidence\Facts;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Registry
{
    public static function serial(?string $value): ?string
    {
        return filled($value) ? mb_strtoupper(preg_replace('/\s+/u', ' ', trim($value))) : null;
    }

    public function audit(Asset $a, ?User $u, string $action, ?array $before = null, ?array $after = null, ?string $note = null): void
    {
        AssetAudit::create(['organization_id' => $a->organization_id, 'asset_id' => $a->id, 'actor_id' => $u?->id, 'action' => $action, 'before_data' => $before, 'after_data' => $after, 'justification' => $note, 'created_at' => now()]);
    }

    public function lock(Property $p, ?Asset $a = null): ?Asset
    {
        Property::whereKey($p->id)->lockForUpdate()->firstOrFail();

        return $a ? Asset::whereKey($a->id)->lockForUpdate()->firstOrFail() : null;
    }

    public function revision(Asset $a, int $revision): void
    {
        if ($a->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => __('asset.stale')]);
        }
    }

    public function writable(User $u, Asset $a, string $ability = 'update'): void
    {
        Gate::forUser($u)->authorize($ability, $a);
        if ($a->archived_at) {
            throw ValidationException::withMessages(['asset' => __('asset.archived_readonly')]);
        }
    }

    public function duplicates(Asset $a, ?string $serial): Builder
    {
        $key = self::serial($serial);

        return Asset::where('property_id', $a->property_id)->available()->where('id', '!=', $a->id ?? 0)->where(function ($q) use ($key) {
            if ($key === null) {
                $q->whereRaw('1=0');

                return;
            }$q->where('serial_key', $key)->orWhereHas('applications', fn ($q) => $q->where('field', 'serial_number')->where('serial_key', $key)->where('status', 'active'));
        });
    }

    public function save(User $u, Property $p, array $input, ?Asset $asset = null): Asset
    {
        return DB::transaction(function () use ($u, $p, $input, $asset) {
            $a = $this->lock($p, $asset);
            if ($a) {
                $this->writable($u, $a);
                abort_unless($a->property_id === $p->id, 404);
            } else {
                Gate::forUser($u)->authorize('create', [Asset::class, $p]);
            }
            if (isset($input['served_spaces']) && is_array($input['served_spaces'])) {
                $input['served_spaces'] = array_values(array_filter($input['served_spaces'], fn ($id) => $id !== null && $id !== ''));
            }
            $rules = ['name' => 'required|string|max:150', 'asset_type_id' => 'required|integer', 'revision' => $a ? 'required|integer|min:1' : 'nullable|integer', 'building_id' => 'nullable|integer', 'floor_id' => 'nullable|integer', 'space_id' => 'nullable|integer', 'system_id' => 'nullable|integer', 'external_reference' => 'nullable|string|max:100', 'is_system' => 'sometimes|boolean', 'status' => ['required', Rule::in(config('assets.statuses'))], 'attributes' => 'nullable|array|max:20', 'served_spaces' => 'nullable|array|max:100', 'served_spaces.*' => 'integer|distinct', 'duplicates_ack' => 'sometimes|accepted'];
            foreach (['manufacturer', 'model', 'serial_number', 'purchase_vendor'] as $key) {
                $rules[$key] = 'nullable|string|max:160';
            }foreach (['installation_date', 'purchase_date', 'warranty_start', 'warranty_end'] as $key) {
                $rules[$key] = 'nullable|date_format:Y-m-d';
            }$rules += ['purchase_amount' => 'nullable|numeric|min:0|max:999999999999', 'purchase_currency' => 'nullable|required_with:purchase_amount|string|regex:/^[A-Z]{3}$/', 'warranty_terms' => 'nullable|string|max:2000', 'notes' => 'nullable|string|max:4000'];
            $d = Validator::make($input, $rules)->validate();
            $d['name'] = Facts::clean($d['name'], 150);
            if (! filled($d['name'])) {
                throw ValidationException::withMessages(['name' => __('asset.name_type_required')]);
            }if ($a) {
                $this->revision($a, (int) $d['revision']);
            }
            $type = AssetType::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $p->organization_id))->findOrFail($d['asset_type_id']);
            if ($type->archived_at && (! $a || $a->asset_type_id !== $type->id)) {
                throw ValidationException::withMessages(['asset_type_id' => __('asset.type_unavailable')]);
            }
            $location = $this->location($p, $d);
            $system = isset($d['system_id']) ? Asset::where('property_id', $p->id)->available()->whereNull('archived_at')->where('is_system', true)->findOrFail($d['system_id']) : null;
            if ($system) {
                $seen = [$a?->id];
                $parent = $system;
                for ($i = 0; $parent; $i++) {
                    if ($i >= 100 || in_array($parent->id, $seen, true)) {
                        throw ValidationException::withMessages(['system_id' => __('asset.cycle')]);
                    }$seen[] = $parent->id;
                    $parent = $parent->system;
                }
            }
            if ($a && $a->is_system && ! ($d['is_system'] ?? false) && $a->components()->exists()) {
                throw ValidationException::withMessages(['is_system' => __('asset.system_has_components')]);
            }
            $spaces = Space::where('property_id', $p->id)->whereIn('id', $d['served_spaces'] ?? [])->where(fn ($q) => $q->whereNull('building_id')->orWhereHas('building'))->get();
            if ($spaces->count() !== count($d['served_spaces'] ?? [])) {
                throw ValidationException::withMessages(['served_spaces' => __('asset.location_invalid')]);
            }
            $manual = $a?->manual_data ?? [];
            foreach (config('assets.fields') as $field) {
                $value = $d[$field] ?? null;
                $manual[$field] = is_string($value) ? Facts::clean($value, $field === 'notes' ? 4000 : 2000) : $value;
            }
            if ($manual['purchase_amount'] !== null) {
                $manual['purchase_amount'] = number_format((float) $manual['purchase_amount'], 2, '.', '');
            }
            $manual['attributes'] = array_replace($manual['attributes'] ?? [], $this->attributes($type, $d['attributes'] ?? []));
            $old = $a ? $a->attributesToArray() + ['served_spaces' => $a->servedSpaces()->pluck('spaces.id')->all()] : null;
            $a ??= new Asset(['organization_id' => $p->organization_id, 'property_id' => $p->id]);
            if (self::serial($manual['serial_number']) !== $a->serial_key && $this->duplicates($a, $manual['serial_number'])->exists() && ! isset($d['duplicates_ack'])) {
                throw ValidationException::withMessages(['serial_number' => __('asset.duplicate_ack')]);
            }
            $a->fill(['name' => Facts::clean($d['name'], 150), 'asset_type_id' => $type->id, 'external_reference' => self::serial($d['external_reference'] ?? null), 'manual_data' => $manual, 'serial_key' => self::serial($manual['serial_number']), 'status' => $d['status'], 'is_system' => (bool) ($d['is_system'] ?? false), 'system_id' => $system?->id, ...$location]);
            if (! $a->exists) {
                $a->created_by = $u->id;
            } else {
                $a->revision++;
            }$a->save();
            $a->servedSpaces()->sync($spaces->modelKeys());
            $this->audit($a, $u, $old ? 'updated' : 'created', $old, $a->attributesToArray() + ['served_spaces' => $spaces->modelKeys()]);

            return $a;
        });
    }

    private function attributes(AssetType $type, array $input): array
    {
        $schema = collect($type->attribute_schema)->keyBy('key');
        foreach (array_keys($input) as $key) {
            if (! $schema->has($key)) {
                throw ValidationException::withMessages(['attributes' => __('asset.attributes_invalid')]);
            }
        }$result = [];
        foreach ($schema as $key => $spec) {
            if (! array_key_exists($key, $input)) {
                continue;
            }$value = $input[$key] ?? null;
            if ($value === '' || $value === null) {
                $result[$key] = null;

                continue;
            }$rule = match ($spec['type']) {
                'number' => 'numeric','integer' => 'integer','boolean' => Rule::in(['true', 'false']),'date' => 'date_format:Y-m-d',default => 'string|max:1000'
            };
            Validator::make(['attributes' => [$key => $value]], ['attributes.'.$key => ['bail', 'required', $rule]])->validate();
            $result[$key] = match ($spec['type']) {
                'number' => (float) $value,'integer' => (int) $value,'boolean' => $value === 'true',default => Facts::clean((string) $value, 1000)
            };
            if (is_float($result[$key]) && ! is_finite($result[$key])) {
                throw ValidationException::withMessages(['attributes' => __('asset.attributes_invalid')]);
            }
        }

        return $result;
    }

    private function location(Property $p, array $d): array
    {
        $space = isset($d['space_id']) ? Space::where('property_id', $p->id)->findOrFail($d['space_id']) : null;
        $floor = isset($d['floor_id']) ? Floor::findOrFail($d['floor_id']) : ($space?->floor_id ? Floor::findOrFail($space->floor_id) : null);
        $building = isset($d['building_id']) ? Building::where('property_id', $p->id)->findOrFail($d['building_id']) : (($space?->building_id ?? $floor?->building_id) ? Building::where('property_id', $p->id)->findOrFail($space?->building_id ?? $floor->building_id) : null);
        if (($floor && (! $building || $floor->building_id !== $building->id)) || ($space && ($space->building_id !== $building?->id || ($floor && $space->floor_id !== $floor->id))) || $building?->state === 'archived') {
            throw ValidationException::withMessages(['space_id' => __('asset.location_invalid')]);
        }

        return ['building_id' => $building?->id, 'floor_id' => $floor?->id, 'space_id' => $space?->id];
    }

    public function archive(User $u, Asset $asset, int $revision, bool $archive): Asset
    {
        return DB::transaction(function () use ($u, $asset, $revision, $archive) {
            $a = $this->lock($asset->property, $asset);
            Gate::forUser($u)->authorize('update', $a);
            $this->revision($a, $revision);
            $before = $a->attributesToArray();
            $a->archived_at = $archive ? now() : null;
            $a->revision++;
            $a->save();
            $this->audit($a, $u, $archive ? 'archived' : 'restored', $before, $a->attributesToArray());

            return $a;
        });
    }

    public function purgeBuilding(Building $b, ?User $u): void
    {
        $ids = DB::table('spaces')->where('building_id', $b->id)->pluck('id');
        foreach (Asset::where('property_id', $b->property_id)->where('building_id', $b->id)->orderBy('id')->cursor() as $a) {
            $before = $a->attributesToArray();
            $a->fill(['building_id' => null, 'floor_id' => null, 'space_id' => null, 'archived_at' => now(), 'revision' => $a->revision + 1])->save();
            $this->audit($a, $u, 'building_purged', $before, $a->attributesToArray());
        }
        $shared = DB::table('asset_served_spaces')->whereIn('space_id', $ids)->distinct()->pluck('asset_id');
        foreach (Asset::whereIn('id', $shared)->cursor() as $a) {
            $this->audit($a, $u, 'served_space_purged', null, ['building_uuid' => $b->uuid]);
        }
        $sourceIds = DB::table('evidence_assertions')->where('building_id', $b->id)->select('id');
        $links = DB::table('asset_evidence_links')->whereIn('assertion_id', $sourceIds)->pluck('id');
        DB::table('asset_fact_applications')->whereIn('link_id', $links)->where('status', 'active')->update(['status' => 'withdrawn']);
        $affected = DB::table('asset_evidence_links')->whereIn('id', $links)->pluck('asset_id')->merge(DB::table('asset_document_links')->whereIn('version_id', DB::table('evidence_versions')->where('building_id', $b->id)->select('id'))->pluck('asset_id'))->unique();
        foreach (Asset::whereIn('id', $affected)->cursor() as $a) {
            $this->audit($a, $u, 'source_purged', null, ['building_uuid' => $b->uuid]);
        }
    }
}
