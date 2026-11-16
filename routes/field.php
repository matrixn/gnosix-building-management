<?php

use App\Http\Controllers\Identity\FieldController as Field;
use Illuminate\Support\Facades\Route;

Route::middleware(['customer.locale', 'throttle:field-read'])->group(function () {
    Route::get('/q/{locator}', [Field::class, 'resolve'])->where('locator', '[a-f0-9]{48}')->name('field.qr.resolve');
    Route::get('/field/invitation', [Field::class, 'entry'])->name('field.invitation');
    Route::prefix('field')->middleware(['auth:web', 'customer.session', 'verified'])->group(function () {
        Route::post('preview', [Field::class, 'preview'])->middleware('throttle:field-redeem')->name('field.preview');
        Route::post('redeem', [Field::class, 'redeem'])->middleware('throttle:field-redeem')->name('field.redeem');
        Route::get('/', [Field::class, 'index'])->name('field.index');
        Route::get('work/{uuid}', [Field::class, 'show'])->name('field.work');
        Route::post('work/{uuid}/{action}', [Field::class, 'action'])->whereIn('action', ['report', 'upload', 'state'])->middleware('throttle:organization-draft')->name('field.work.action');
        Route::post('work/{uuid}/reports/{report}/review', [Field::class, 'review'])->middleware('throttle:organization-draft')->name('field.report.review');
        Route::get('work/{uuid}/documents/{version}', [Field::class, 'document'])->middleware('signed:relative')->name('field.document');
        Route::get('work/{uuid}/reports/{report}/pdf', [Field::class, 'reportPdf'])->middleware('signed:relative')->name('field.report.pdf');
    });
});
