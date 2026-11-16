<?php

use App\Http\Controllers\Identity\EvidenceController as Evidence;
use Illuminate\Support\Facades\Route;

Route::get('organizations/{organization}/evidence', [Evidence::class, 'index'])->name('app.evidence.index');
Route::prefix('organizations/{organization}/properties/{property}/buildings/{building:uuid}/memory')->group(function () {
    Route::get('/', [Evidence::class, 'show'])->name('app.evidence.show');
    Route::get('files/{version}', [Evidence::class, 'file'])->middleware('signed:relative')->name('app.evidence.file');
    Route::get('progress/{version}', [Evidence::class, 'progress'])->name('app.evidence.progress');
    Route::middleware('throttle:organization-draft')->group(function () {
        Route::post('upload', [Evidence::class, 'upload'])->name('app.evidence.upload');
        Route::post('assertions', [Evidence::class, 'manual'])->name('app.evidence.manual');
        Route::post('assertions/{assertion}/review', [Evidence::class, 'review'])->name('app.evidence.review');
        Route::post('bulk-review', [Evidence::class, 'bulk'])->name('app.evidence.bulk');
        Route::post('extract/{version}', [Evidence::class, 'extract'])->name('app.evidence.extract');
        Route::post('archive/{document}', [Evidence::class, 'archive'])->name('app.evidence.archive');
    });
});
