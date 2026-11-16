<?php

use App\Http\Controllers\Identity\AssetController as Assets;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}')->group(function () {
    Route::get('assets', [Assets::class, 'index'])->name('app.assets.index');
    Route::get('assets/create', [Assets::class, 'create'])->name('app.assets.create');
    Route::get('assets/lookup', [Assets::class, 'lookup'])->name('app.assets.lookup');
    Route::get('assets/import', [Assets::class, 'import'])->name('app.assets.import');
    Route::get('asset-types', [Assets::class, 'typesIndex'])->name('app.assets.types');
    Route::get('properties/{property}/spaces/{space:uuid}/assets', [Assets::class, 'room'])->name('app.assets.room');
    Route::get('assets/{asset:uuid}', [Assets::class, 'show'])->name('app.assets.show');
    Route::get('assets/{asset:uuid}/edit', [Assets::class, 'edit'])->name('app.assets.edit');
    Route::get('assets/{asset:uuid}/photos/{photo:uuid}', [Assets::class, 'photo'])->middleware('signed:relative')->name('app.assets.photo');
    Route::middleware('throttle:organization-draft')->group(function () {
        Route::post('assets', [Assets::class, 'store'])->name('app.assets.store');
        Route::patch('assets/{asset:uuid}', [Assets::class, 'update'])->name('app.assets.update');
        Route::post('assets/import', [Assets::class, 'importCommit'])->name('app.assets.import.commit');
        Route::post('assets/{asset:uuid}/archive', [Assets::class, 'archive'])->name('app.assets.archive');
        Route::post('assets/{asset:uuid}/assertions', [Assets::class, 'link'])->name('app.assets.link');
        Route::post('assets/{asset:uuid}/documents', [Assets::class, 'document'])->name('app.assets.document');
        Route::post('assets/{asset:uuid}/links/{link}/review', [Assets::class, 'review'])->name('app.assets.review');
        Route::post('assets/{asset:uuid}/photos', [Assets::class, 'photos'])->name('app.assets.photos');
        Route::post('assets/{asset:uuid}/photos/{photo:uuid}/archive', [Assets::class, 'photoArchive'])->name('app.assets.photos.archive');
        Route::post('asset-types', [Assets::class, 'typeSave'])->name('app.assets.types.store');
        Route::patch('asset-types/{type}', [Assets::class, 'typeSave'])->name('app.assets.types.update');
    });
});
