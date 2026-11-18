<?php

use App\Evidence\Retention;
use App\Jobs\GenerateMaintenance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gnosix:evidence-cleanup', function () {
    $this->info(app(Retention::class)->cleanup().' evidence files cleaned.');
})->purpose('Retry committed private evidence file deletions');
Schedule::command('gnosix:evidence-cleanup')->everyMinute()->withoutOverlapping();

Artisan::command('gnosix:maintenance-generate', function () {
    GenerateMaintenance::dispatch();
    $this->info('Maintenance generation queued.');
})->purpose('Queue idempotent maintenance work order generation');
Schedule::command('gnosix:maintenance-generate')->hourly()->withoutOverlapping();

Artisan::command('gnosix:campaign-cleanup', function () {
    $count = 0;
    DB::table('campaign_visits')->where('expires_at', '<=', now())->chunkById(500, function ($visits) use (&$count) {
        $count += DB::table('campaign_visits')->whereIn('id', $visits->pluck('id'))->delete();
    });
    $this->info($count.' expired campaign visits cleaned.');
})->purpose('Remove expired campaign visit identifiers while preserving aggregate counts');
Schedule::command('gnosix:campaign-cleanup')->hourly()->withoutOverlapping();
