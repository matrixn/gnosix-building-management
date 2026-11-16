<?php

namespace App\Models;

class MaintenancePlan extends MaintenanceRecord
{
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'plan_id');
    }
}
