<?php

use App\Http\Controllers\Identity\HandoverRecipientController as Recipient;
use Illuminate\Support\Facades\Route;

Route::prefix('handover')->middleware(['customer.locale', 'throttle:field-read'])->name('handover.')->group(function () {
    Route::view('invitation', 'trust.invitation')->name('invitation');
    Route::middleware(['auth:web', 'customer.session', 'verified'])->group(function () {
        Route::post('redeem', [Recipient::class, 'redeem'])->middleware('throttle:field-redeem')->name('redeem');
        Route::get('/', [Recipient::class, 'index'])->name('portal');
        Route::get('{flow}', [Recipient::class, 'show'])->whereUuid('flow')->name('show');
        Route::post('{flow}/review/{action}', [Recipient::class, 'action'])->whereUuid('flow')->whereIn('action', ['accept', 'return', 'question'])
            ->middleware('throttle:organization-draft')->name('action');
        Route::get('{flow}/documents/{version}', [Recipient::class, 'document'])->whereUuid(['flow', 'version'])->name('document');
        Route::get('{flow}/export/{format}', [Recipient::class, 'export'])->whereUuid('flow')->whereIn('format', ['pdf', 'json', 'zip'])
            ->middleware('throttle:customer-sensitive')->name('export');
    });
});
