<?php

use App\Http\Controllers\Identity\FieldController as Field;
use App\Http\Controllers\Identity\QrController as Qr;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}')->group(function () {
    Route::get('field-access', [Field::class, 'manage'])->name('app.field.manage');
    Route::get('qr', [Qr::class, 'index'])->name('app.qr.index');
    Route::get('qr/{identity}/{format}', [Qr::class, 'image'])->name('app.qr.image');
    Route::middleware('throttle:organization-draft')->group(function () {
        Route::post('qr', [Qr::class, 'mutate'])->name('app.qr.mutate');
        Route::post('qr/labels', [Qr::class, 'labels'])->name('app.qr.labels');
        Route::post('field-access', [Field::class, 'invite'])->name('app.field.invite');
        Route::post('field-access/{invitation}/revoke', [Field::class, 'revoke'])->name('app.field.revoke');
        Route::post('field-access/{invitation}/documents', [Field::class, 'share'])->name('app.field.share');
        Route::delete('field-access/{invitation}/documents/{document}', [Field::class, 'unshare'])->name('app.field.unshare');
    });
});
