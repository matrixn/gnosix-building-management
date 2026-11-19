<?php

use App\Http\Controllers\Identity\TrustController as Trust;
use Illuminate\Support\Facades\Route;

Route::get('organizations/{organization}/passports', [Trust::class, 'index'])->name('app.trust.index');
Route::prefix('organizations/{organization}/properties/{property}/buildings/{building:uuid}/passport')->group(function () {
    Route::get('/', [Trust::class, 'show'])->name('app.trust.show');
    Route::get('export/{format}', [Trust::class, 'export'])->whereIn('format', ['pdf', 'json', 'zip'])->middleware('throttle:customer-sensitive')->name('app.trust.export');
    Route::get('handover/{flow}', [Trust::class, 'flow'])->whereUuid('flow')->name('app.trust.flow');
    Route::middleware('throttle:organization-draft')->group(function () {
        Route::post('handover', [Trust::class, 'draft'])->name('app.trust.draft');
        Route::post('handover/{flow}/{action}', [Trust::class, 'action'])->whereUuid('flow')
            ->whereIn('action', ['prepare', 'invite', 'complete', 'cancel', 'revoke', 'reply'])->name('app.trust.action');
    });
});
