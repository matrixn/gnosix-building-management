<?php

namespace App\Support;

use App\Models\Building;
use App\Models\BuildingAssertion;
use App\Models\BuildingRevision;
use Illuminate\Support\Facades\DB;

class BuildingAudit
{
    private static int $muted = 0;

    public static function muted(): bool
    {
        return self::$muted > 0;
    }

    public static function without(callable $work): mixed
    {
        self::$muted++;
        try {
            return $work();
        } finally {
            self::$muted--;
        }
    }

    public static function record(Building $b, string $action, string $entity, ?string $key, ?array $before, ?array $after, string $source = 'manual', bool $bump = true): BuildingRevision
    {
        if ($bump) {
            DB::table('buildings')->where('id', $b->id)->increment('declaration_version', 1, ['verification_state' => 'unverified', 'updated_at' => now()]);
        }
        $r = BuildingRevision::create(['building_id' => $b->id, 'actor_id' => auth('web')->id(), 'action' => $action, 'source_kind' => $source, 'entity_type' => $entity, 'entity_reference' => $key, 'before_data' => $before, 'after_data' => $after, 'created_at' => now()]);
        if (in_array($entity, ['building', 'declaration'], true)) {
            $old = $before['identity'] ?? $before ?? [];
            $new = $after['identity'] ?? $after ?? [];
            foreach (config('building-studio.identity_fields') as $field) {
                if (array_key_exists($field, $new) && ($new[$field] ?? null) !== ($old[$field] ?? null)) {
                    BuildingAssertion::create(['building_id' => $b->id, 'revision_id' => $r->id, 'field_key' => $field, 'value' => $new[$field], 'source_kind' => $source, 'verification_state' => 'unverified', 'created_at' => now()]);
                }
            }
        }

        return $r;
    }
}
