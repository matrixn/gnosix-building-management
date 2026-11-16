<?php

namespace App\Policies;

use App\Field\Access;
use App\Models\MaintenanceIssue;
use App\Models\MaintenanceRecord;
use App\Models\Property;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\CapabilityService;
use Illuminate\Support\Facades\Gate;

class MaintenancePolicy
{
    public function report(User $u, Property $p): bool
    {
        return Gate::forUser($u)->allows('view', $p) && app(CapabilityService::class)->allows($u, $p->organization, 'hospitality.maintenance');
    }

    public function manage(User $u, Property $p): bool
    {
        return $this->report($u, $p) && Gate::forUser($u)->allows('update', $p);
    }

    public function view(User $u, MaintenanceRecord $m): bool
    {
        if ($m instanceof WorkOrder && app(Access::class)->grant($u, $m)) {
            return true;
        }

        return $m->organization_id === $m->property->organization_id && $this->report($u, $m->property) && (! $m->building_id || $m->building()->exists()) && (! ($m instanceof MaintenanceIssue) || $m->state !== 'draft' || $m->created_by === $u->id || $this->manage($u, $m->property));
    }

    public function update(User $u, MaintenanceRecord $m): bool
    {
        if ($m instanceof WorkOrder && app(Access::class)->grant($u, $m)) {
            return ! in_array($m->state, ['verified', 'closed', 'cancelled']);
        }

        return $this->view($u, $m) && ($this->manage($u, $m->property) || ($m instanceof WorkOrder && $m->assigned_to === $u->id && $m->organization->memberships()->where('user_id', $u->id)->value('role') !== 'reporter') || ($m instanceof MaintenanceIssue && $m->created_by === $u->id && $m->state === 'draft'));
    }

    public function attach(User $u, MaintenanceRecord $m): bool
    {
        if ($m instanceof WorkOrder && app(Access::class)->grant($u, $m)) {
            return ! in_array($m->state, ['verified', 'closed', 'cancelled']);
        }

        return $this->view($u, $m) && ($this->manage($u, $m->property) || ($m instanceof MaintenanceIssue && $m->created_by === $u->id) || ($m instanceof WorkOrder && $m->assigned_to === $u->id));
    }
}
