<?php

use App\Http\Controllers\Identity\BuildingDeclarationController as Studio;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}/properties/{property}')->group(function () {
    Route::get('building-studio', [Studio::class, 'create'])->name('app.declarations.new');
    Route::get('declaration-drafts/{draft}', [Studio::class, 'editor'])->name('app.declarations.editor');
    Route::post('declaration-drafts/{draft}', [Studio::class, 'save'])->middleware('throttle:organization-draft')->name('app.declarations.draft');
    Route::post('declaration-drafts/{draft}/complete', [Studio::class, 'save'])->middleware('throttle:customer-sensitive')->name('app.declarations.commit');
    Route::get('declarations/{building:uuid}', [Studio::class, 'show'])->name('app.declarations.show');
    Route::get('declarations/{building:uuid}/edit', [Studio::class, 'edit'])->name('app.declarations.edit');
    Route::get('declarations/{building:uuid}/export', [Studio::class, 'export'])->name('app.declarations.export');
    Route::get('declarations/{building:uuid}/revisions/{revision}/export', [Studio::class, 'export'])->name('app.declarations.revision');
});
