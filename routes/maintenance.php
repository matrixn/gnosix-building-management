<?php

use App\Http\Controllers\Identity\MaintenanceController as Maintenance;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}/maintenance')->group(function () {
    Route::get('/', [Maintenance::class, 'index'])->name('app.maintenance.index');
    Route::get('/lookup', [Maintenance::class, 'lookup'])->name('app.maintenance.lookup');
    foreach (['issue', 'work', 'plan'] as $kind) {
        Route::get("/$kind/create", [Maintenance::class, 'form'])->defaults('kind', $kind)->name("app.maintenance.$kind.create");
        Route::post("/$kind", [Maintenance::class, 'save'])->defaults('kind', $kind)->middleware('throttle:organization-draft')->name("app.maintenance.$kind.store");
        Route::get("/$kind/{uuid}", [Maintenance::class, 'show'])->defaults('kind', $kind)->name("app.maintenance.$kind.show");
        Route::get("/$kind/{uuid}/edit", [Maintenance::class, 'form'])->defaults('kind', $kind)->name("app.maintenance.$kind.edit");
        Route::patch("/$kind/{uuid}", [Maintenance::class, 'save'])->defaults('kind', $kind)->middleware('throttle:organization-draft')->name("app.maintenance.$kind.update");
        Route::post("/$kind/{uuid}/{action}", [Maintenance::class, 'action'])->defaults('kind', $kind)->whereIn('action', ['transition', 'perform', 'comment', 'upload', 'link', 'candidate'])->middleware('throttle:organization-draft')->name("app.maintenance.$kind.action");
        Route::get("/$kind/{uuid}/files/{attachment}", [Maintenance::class, 'file'])->defaults('kind', $kind)->middleware('signed:relative')->name("app.maintenance.$kind.file");
    }
});
