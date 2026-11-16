<?php

namespace App\Maintenance;

use App\Models\AssetAudit;
use App\Models\Building;
use App\Models\FieldInvitation;
use App\Models\MaintenanceIssue;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;

class Retention
{
    public function purge(Building $b, ?User $u): void
    {
        // Called under the Building Studio property lock, before location links are removed.
        FieldInvitation::where('property_id', $b->property_id)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('building_id', $b->id))
                ->orWhereHas('workOrders', fn ($w) => $w->where('building_id', $b->id)))
            ->orderBy('id')->chunkById(100, function ($grants) use ($u) {
                foreach ($grants as $grant) {
                    $grant->update(['revoked_at' => now()]);
                    AssetAudit::create(['organization_id' => $grant->organization_id, 'property_id' => $grant->property_id, 'asset_id' => $grant->asset_id, 'actor_id' => $u?->id, 'entity_type' => 'field_invite', 'entity_uuid' => $grant->uuid, 'action' => 'field.revoked', 'after_data' => ['reason' => 'building_purged'], 'created_at' => now()]);
                }
            });
        foreach ([MaintenanceIssue::class, MaintenancePlan::class, WorkOrder::class] as $class) {
            $class::where('building_id', $b->id)->orderBy('id')->chunkById(100, function ($items) use ($u) {
                foreach ($items as $m) {
                    $before = ['state' => $m->state, 'building_id' => $m->building_id, 'space_id' => $m->space_id];
                    if ($m instanceof MaintenancePlan) {
                        $m->state = 'suspended';
                    } elseif ($m instanceof WorkOrder && ! in_array($m->state, ['closed', 'cancelled'])) {
                        $m->state = 'blocked';
                        $m->resolved_at = null;
                        $m->verified_at = null;
                        $m->verified_by = null;
                    }$m->building_id = null;
                    $m->space_id = null;
                    $m->revision++;
                    $m->save();
                    app(Workflow::class)->audit($m, $u, 'building_purged', $before);
                }
            });
        }
    }
}
