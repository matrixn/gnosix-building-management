<?php

namespace App\Models;

class MaintenanceIssue extends MaintenanceRecord
{
    public function workOrders()
    {
        return $this->belongsToMany(WorkOrder::class, 'maintenance_issue_work', 'issue_id', 'work_order_id');
    }
}
