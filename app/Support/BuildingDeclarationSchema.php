<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BuildingDeclarationSchema
{
    public function empty(): array
    {
        return ['identity' => ['name' => '', 'display_name' => null, 'reference' => null, 'classification' => null, 'custom_classification' => null, 'address' => null, 'country' => null, 'timezone' => null, 'latitude' => null, 'longitude' => null, 'construction_year' => null, 'state' => 'draft'], 'levels' => [], 'unplaced_spaces' => [], 'units' => []];
    }

    public function validate(array $input, bool $complete = false): array
    {
        if (strlen(json_encode($input, JSON_THROW_ON_ERROR)) > config('building-studio.max_payload_bytes')) {
            throw ValidationException::withMessages(['structure' => __('building.too_large')]);
        }
        $top = Validator::make($input, ['identity' => ['required', 'array'], 'levels' => ['present', 'array', 'max:'.config('building-studio.max_levels')], 'unplaced_spaces' => ['present', 'array', 'max:5000'], 'units' => ['present', 'array', 'max:'.config('building-studio.max_units')]])->validate();
        if (is_string($top['identity']['country'] ?? null)) {
            $top['identity']['country'] = strtoupper($top['identity']['country']);
        }
        $identity = Validator::make($top['identity'], ['name' => [$complete ? 'required' : 'nullable', 'string', 'max:150'], 'display_name' => ['nullable', 'string', 'max:150'], 'reference' => ['nullable', 'string', 'max:40', 'regex:/^[\pL\pN _\.\-]+$/u'], 'classification' => ['nullable', Rule::in(array_keys(config('building-studio.presets')))], 'custom_classification' => ['nullable', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:500'], 'country' => WorkspacePreferences::countryRules(false), 'timezone' => ['nullable', 'string', Rule::in(\DateTimeZone::listIdentifiers())], 'construction_year' => ['nullable', 'integer', 'min:1', 'max:'.date('Y')], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'state' => ['required', Rule::in(['draft', 'active', 'archived'])]], [], collect(config('building-studio.identity_fields'))->mapWithKeys(fn ($k) => [$k => __('building.'.$k)])->all())->validate();
        $identity = array_replace($this->empty()['identity'], $identity);
        $identity['name'] ??= '';
        foreach ($identity as $k => $v) {
            if (is_string($v)) {
                $identity[$k] = trim($v);
            }
        }if (filled($identity['country'])) {
            $identity['country'] = strtoupper($identity['country']);
        }if ($complete && $identity['classification'] !== 'custom') {
            $identity['custom_classification'] = null;
        }
        if (($identity['latitude'] === null) !== ($identity['longitude'] === null)) {
            throw ValidationException::withMessages(['coordinates' => __('building.coordinates_pair')]);
        }
        if ($complete && $identity['classification'] === 'custom' && ! filled($identity['custom_classification'])) {
            throw ValidationException::withMessages(['custom_classification' => __('building.custom_required')]);
        }
        $sets = ['level' => [], 'space' => [], 'bed' => [], 'unit' => []];
        $spaceBeds = [];
        $count = 0;
        $bedCount = 0;
        $unique = function (string $type, string $key) use (&$sets) {
            if (isset($sets[$type][$key])) {
                throw ValidationException::withMessages(['structure' => __('building.duplicate_key')]);
            }$sets[$type][$key] = true;
        };
        $validateSpaces = function (array $spaces, string $path) use (&$sets, &$count, &$bedCount, &$spaceBeds, $unique) {
            $out = [];
            foreach ($spaces as $i => $space) {
                $s = $this->row($space, ['key' => 'required|uuid', 'name' => 'required|string|max:150', 'space_type' => ['required', Rule::in(config('building-studio.space_types'))], 'number' => 'nullable|string|max:40', 'bed_count' => 'nullable|integer|min:0|max:1000', 'capacity' => 'nullable|integer|min:0|max:1000', 'area_m2' => 'nullable|numeric|min:0|max:100000000', 'beds' => 'present|array|max:1000'], "$path.$i");
                $s['key'] = strtolower($s['key']);
                $unique('space', $s['key']);
                $count++;
                $beds = [];
                foreach ($s['beds'] as $j => $bed) {
                    $b = $this->row($bed, ['key' => 'required|uuid', 'label' => 'required|string|max:80'], "$path.$i.beds.$j");
                    $b['key'] = strtolower($b['key']);
                    $unique('bed', $b['key']);
                    $spaceBeds[$b['key']] = $s['key'];
                    $beds[] = $b;
                    $bedCount++;
                }
                $s = array_replace(['number' => null, 'bed_count' => null, 'capacity' => null, 'area_m2' => null], $s);
                $s['beds'] = $beds;
                if ($beds && $s['bed_count'] !== null && (int) $s['bed_count'] !== count($beds)) {
                    throw ValidationException::withMessages(["$path.$i.bed_count" => __('building.bed_mismatch')]);
                }
                if ($beds) {
                    $s['bed_count'] = count($beds);
                }$out[] = $s;
            }

return $out;
        };
        $levels = [];
        foreach ($top['levels'] as $i => $level) {
            $l = $this->row($level, ['key' => 'required|uuid', 'name' => 'required|string|max:150', 'kind' => ['nullable', Rule::in(config('building-studio.level_kinds'))], 'spaces' => 'present|array|max:5000'], "levels.$i");
            $l['key'] = strtolower($l['key']);
            $unique('level', $l['key']);
            $l['kind'] ??= null;
            $l['spaces'] = $validateSpaces($l['spaces'], "levels.$i.spaces");
            $levels[] = $l;
        }
        $unplaced = $validateSpaces($top['unplaced_spaces'], 'unplaced_spaces');
        if ($count > config('building-studio.max_spaces') || $bedCount > config('building-studio.max_beds')) {
            throw ValidationException::withMessages(['structure' => __('building.too_large')]);
        }
        $units = [];
        foreach ($top['units'] as $i => $unit) {
            $u = $this->row($unit, ['key' => 'required|uuid', 'name' => 'required|string|max:150', 'unit_type' => ['required', Rule::in(config('building-studio.unit_types'))], 'space_keys' => 'present|array|max:5000', 'bed_key' => 'nullable|uuid'], "units.$i");
            $u['key'] = strtolower($u['key']);
            $u['space_keys'] = array_map(fn ($v) => is_string($v) ? strtolower($v) : $v, $u['space_keys']);
            if (is_string($u['bed_key'] ?? null)) {
                $u['bed_key'] = strtolower($u['bed_key']);
            }$unique('unit', $u['key']);
            $seen = [];
            foreach ($u['space_keys'] as $key) {
                if (! is_string($key) || ! isset($sets['space'][$key]) || isset($seen[$key])) {
                    throw ValidationException::withMessages(["units.$i.space_keys" => __('building.unit_spaces')]);
                }$seen[$key] = true;
            }
            $u['bed_key'] ??= null;
            if ($complete && $u['unit_type'] !== 'whole_building' && ! $seen) {
                throw ValidationException::withMessages(["units.$i.space_keys" => __('building.unit_spaces')]);
            }
            if ($u['unit_type'] === 'bed' && ($complete || $u['bed_key']) && (! $u['bed_key'] || ! isset($spaceBeds[$u['bed_key']]) || count($seen) !== 1 || ! isset($seen[$spaceBeds[$u['bed_key']]]))) {
                throw ValidationException::withMessages(["units.$i.bed_key" => __('building.unit_bed')]);
            }
            if ($u['unit_type'] !== 'bed' && $u['bed_key']) {
                throw ValidationException::withMessages(["units.$i.bed_key" => __('building.unit_bed')]);
            }
            $units[] = $u;
        }

        return ['identity' => $identity, 'levels' => $levels, 'unplaced_spaces' => $unplaced, 'units' => $units];
    }

    private function row(mixed $row, array $rules, string $path): array
    {
        if (! is_array($row)) {
            throw ValidationException::withMessages([$path => __('building.invalid_structure')]);
        }
        $validator = Validator::make($row, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($messages, $key) => [$path.'.'.$key => $messages])->all());
        }

        return $validator->validated();
    }
}
