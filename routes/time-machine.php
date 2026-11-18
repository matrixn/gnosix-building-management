<?php

use App\Http\Controllers\Identity\TimeMachineController;
use Illuminate\Support\Facades\Route;

Route::get('organizations/{organization}/time-machine', [TimeMachineController::class, 'index'])->name('app.time-machine.index');
Route::prefix('organizations/{organization}/properties/{property}/buildings/{building:uuid}/time-machine')
    ->middleware('throttle:dna-read')->group(function () {
        Route::get('/', [TimeMachineController::class, 'show'])->withTrashed()->name('app.time-machine.show');
        Route::get('comparison.pdf', [TimeMachineController::class, 'export'])->withTrashed()->middleware('throttle:customer-sensitive')->name('app.time-machine.export');
    });
