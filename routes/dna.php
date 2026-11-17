<?php

use App\Http\Controllers\Identity\DnaController as Dna;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}')->group(function () {
    Route::get('dna', [Dna::class, 'index'])->name('app.dna.index');
    Route::prefix('properties/{property}/dna')->middleware('throttle:dna-read')->group(function () {
        foreach (['graph', 'search', 'node', 'relation', 'simulate', 'sources'] as $method) {
            Route::get($method, [Dna::class, $method])->name('app.dna.'.$method);
        }
        Route::middleware('throttle:organization-draft')->group(function () {
            Route::post('relations', [Dna::class, 'store'])->name('app.dna.store');
            Route::patch('relations/{relation}', [Dna::class, 'update'])->name('app.dna.update');
            Route::post('relations/{relation}/review', [Dna::class, 'review'])->name('app.dna.review');
            Route::post('types', [Dna::class, 'type'])->name('app.dna.type');
            Route::post('insights', [Dna::class, 'insight'])->name('app.dna.insight');
        });
    });
});
