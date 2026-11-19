<?php

use App\Http\Controllers\Identity\OperationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}/operations')->group(function () {
    Route::get('/', [OperationsController::class, 'index'])->name('app.operations.index');
    Route::post('preferences', [OperationsController::class, 'preferences'])->middleware('throttle:customer-sensitive')->name('app.operations.preferences');
    Route::post('reports', [OperationsController::class, 'export'])->middleware('throttle:customer-sensitive')->name('app.operations.export');
    Route::get('reports/{report}', [OperationsController::class, 'download'])->whereUuid('report')->middleware('throttle:customer-sensitive')->name('app.operations.download');
});
