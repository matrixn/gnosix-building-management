<?php

namespace App\Models;

class WorkOrder extends MaintenanceRecord
{
    public function issues()
    {
        return $this->belongsToMany(MaintenanceIssue::class, 'maintenance_issue_work', 'work_order_id', 'issue_id');
    }

    public function plan()
    {
        return $this->belongsTo(MaintenancePlan::class, 'plan_id');
    }

    public function comments()
    {
        return $this->hasMany(MaintenanceComment::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
