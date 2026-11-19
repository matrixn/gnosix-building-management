<?php

use App\Evidence\Retention;
use App\Jobs\GenerateMaintenance;
use App\Jobs\SendOperationsDigest;
use App\Operations\Reports;
use Carbon\CarbonImmutable;
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

Artisan::command('gnosix:operations-digest', function () {
    $week = CarbonImmutable::now('Europe/Bucharest')->startOfWeek()->toDateString();
    $preferences = DB::table('operations_preferences')->where('weekly_digest', true)
        ->where(fn ($query) => $query->whereNull('last_digest_week')->orWhere('last_digest_week', '<', $week))
        ->orderBy('organization_id')->orderBy('user_id');
    foreach ($preferences->lazy(250) as $preference) {
        SendOperationsDigest::dispatch($preference->organization_id, $preference->user_id, $week);
    }
})->purpose('Queue opted-in hospitality operations digests');
Schedule::command('gnosix:operations-digest')->weeklyOn(1, '08:00')->timezone('Europe/Bucharest')->withoutOverlapping();

Artisan::command('gnosix:operations-cleanup', fn () => app(Reports::class)->cleanup())
    ->purpose('Remove expired private operations reports');
Schedule::command('gnosix:operations-cleanup')->hourly()->withoutOverlapping();
