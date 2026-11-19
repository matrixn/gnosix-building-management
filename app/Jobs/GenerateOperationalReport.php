<?php

namespace App\Jobs;

use App\Operations\Reports;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GenerateOperationalReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public string $report)
    {
        $this->onQueue('maintenance');
    }

    public function handle(Reports $reports): void
    {
        $reports->generate($this->report);
    }

    public function failed(): void
    {
        DB::table('operational_reports')->where('id', $this->report)->whereIn('state', ['queued', 'processing'])
            ->update(['state' => 'failed', 'updated_at' => now()]);
    }
}
