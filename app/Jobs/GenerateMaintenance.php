<?php

namespace App\Jobs;

use App\Maintenance\Workflow;
use App\Models\MaintenancePlan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMaintenance implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onConnection('database')->onQueue('maintenance');
    }

    public function handle(Workflow $flow): void
    {
        MaintenancePlan::where('state', 'active')->whereNotNull('next_on')->whereDate('next_on', '<=', now()->addDay()->toDateString())->orderBy('id')->chunkById(100, function ($plans) use ($flow) {
            foreach ($plans as $p) {
                $flow->generate($p);
            }
        });
    }
}
