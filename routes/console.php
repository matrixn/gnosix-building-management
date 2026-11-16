<?php

use App\Evidence\Retention;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gnosix:evidence-cleanup', function () {
    $this->info(app(Retention::class)->cleanup().' evidence files cleaned.');
})->purpose('Retry committed private evidence file deletions');
Schedule::command('gnosix:evidence-cleanup')->everyMinute()->withoutOverlapping();
