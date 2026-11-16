<?php

namespace App\Evidence;

use App\Models\Building;
use App\Models\Space;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Facts
{
    public static function clean(?string $text, int $max = 4000): ?string
    {
        return $text === null ? null : mb_substr(trim(strip_tags(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text))), 0, $max);
    }

    public function validate(Building $building, array $input): array
    {
        $d = Validator::make($input, ['semantic_key' => ['required', Rule::in(array_keys(config('evidence.keys')))], 'subject_type' => ['required', Rule::in(['building', 'space', 'equipment'])], 'subject_reference' => 'nullable|string|max:100', 'space_id' => 'nullable|integer', 'value' => 'required|string|max:4000', 'quote' => 'nullable|string|max:4000', 'page' => 'nullable|integer|min:1|max:300', 'region' => 'nullable|array|size:4', 'region.*' => 'numeric|between:0,1', 'confidence' => 'nullable|numeric|between:0,1', 'effective_on' => 'nullable|date_format:Y-m-d'])->validate();
        $key = $d['semantic_key'];
        $type = config('evidence.keys')[$key];
        $raw = self::clean($d['value']);
        $value = $raw;
        if ($type === 'integer' && ! preg_match('/^-?\d+$/', $raw)) {
            throw ValidationException::withMessages(['value' => __('evidence.invalid_value')]);
        }
        if (in_array($type, ['number', 'integer'], true)) {
            if (! is_numeric($raw) || ! is_finite((float) $raw)) {
                throw ValidationException::withMessages(['value' => __('evidence.invalid_value')]);
            }$value = $type === 'integer' ? (int) $raw : round((float) $raw, 6);
        }
        if ($type === 'boolean') {
            if (! in_array(mb_strtolower($raw), ['true', 'false', '1', '0'], true)) {
                throw ValidationException::withMessages(['value' => __('evidence.invalid_value')]);
            }$value = in_array(mb_strtolower($raw), ['true', '1'], true);
        }
        if ($type === 'date' && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) || ! checkdate((int) substr($raw, 5, 2), (int) substr($raw, 8, 2), (int) substr($raw, 0, 4)))) {
            throw ValidationException::withMessages(['value' => __('evidence.invalid_value')]);
        }
        if ($raw === '' || ($key === 'building.construction_year' && ($value < 1 || $value > (int) date('Y'))) || ($key === 'space.area_m2' && ($value < 0 || $value > 100000000))) {
            throw ValidationException::withMessages(['value' => __('evidence.invalid_value')]);
        }
        $expected = str_starts_with($key, 'building.') ? 'building' : (str_starts_with($key, 'space.') ? 'space' : (str_starts_with($key, 'equipment.') ? 'equipment' : null));
        if ($expected && $d['subject_type'] !== $expected) {
            throw ValidationException::withMessages(['subject_type' => __('evidence.subject_mismatch')]);
        }
        $subject = $d['subject_type'];
        $reference = self::clean($d['subject_reference'] ?? null, 100);
        $spaceId = null;
        if ($subject === 'building') {
            $reference = $building->uuid;
        } elseif ($subject === 'space') {
            $space = Space::where('building_id', $building->id)->where('property_id', $building->property_id)->find($d['space_id'] ?? null);
            if (! $space) {
                throw ValidationException::withMessages(['space_id' => __('evidence.subject_mismatch')]);
            }$reference = $space->uuid;
            $spaceId = $space->id;
        } elseif (! $reference) {
            throw ValidationException::withMessages(['subject_reference' => __('evidence.subject_mismatch')]);
        } else {
            $reference = mb_strtoupper(preg_replace('/\s+/u', ' ', $reference));
        }
        $region = $d['region'] ?? null;
        if ($region && ($region[0] + $region[2] > 1 || $region[1] + $region[3] > 1 || $region[2] <= 0 || $region[3] <= 0)) {
            throw ValidationException::withMessages(['region' => __('evidence.invalid_value')]);
        }

        return ['subject_type' => $subject, 'subject_reference' => $reference, 'space_id' => $spaceId, 'semantic_key' => $key, 'value_type' => $type, 'value' => $value, 'original_value' => $raw, 'quote' => self::clean($d['quote'] ?? null), 'page' => $d['page'] ?? null, 'region' => $region, 'ai_confidence' => $d['confidence'] ?? null, 'effective_on' => $d['effective_on'] ?? null];
    }

    public static function same(mixed $a, mixed $b, ?string $type = null): bool
    {
        if ($type !== 'string' && is_numeric($a) && is_numeric($b)) {
            return round((float) $a, 6) === round((float) $b, 6);
        }

        return mb_strtoupper(trim((string) $a)) === mb_strtoupper(trim((string) $b));
    }
}
