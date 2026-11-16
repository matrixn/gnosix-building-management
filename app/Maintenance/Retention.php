<?php

namespace App\Maintenance;

use App\Models\Building;
use App\Models\MaintenanceIssue;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;

class Retention
{
    public function purge(Building $b, ?User $u): void
    {
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
