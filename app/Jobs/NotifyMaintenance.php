<?php

namespace App\Jobs;

use App\Models\WorkOrder;
use App\Notifications\MaintenanceAssigned;
use App\Policies\MaintenancePolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyMaintenance implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $workId) {}

    public function handle(): void
    {
        $w = WorkOrder::find($this->workId);
        if (! $w || ! $w->assignee || ! $w->plan?->notify_assignee || ! app(MaintenancePolicy::class)->view($w->assignee, $w)) {
            return;
        }
        $w->assignee->notify(new MaintenanceAssigned($w));
    }
}
