<?php

use App\Http\Controllers\Identity\PropertyController;
use App\Http\Controllers\Identity\WorkspaceSettingsController;
use App\Http\Controllers\Identity\WorkspaceStudioController;
use Illuminate\Support\Facades\Route;

Route::prefix('organizations/{organization}')->group(function () {
    Route::get('studio', [WorkspaceStudioController::class, 'show'])->name('app.studio');
    Route::post('studio/draft', [WorkspaceStudioController::class, 'save'])->middleware('throttle:organization-draft')->name('app.studio.draft');
    Route::get('workspace-settings', [WorkspaceSettingsController::class, 'show'])->name('app.workspace.settings');
    Route::get('logo', [WorkspaceSettingsController::class, 'logo'])->name('app.workspace.logo');
    Route::get('properties', [PropertyController::class, 'index'])->name('app.properties.index');
    Route::get('spaces', [PropertyController::class, 'spaces'])->name('app.spaces.index');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('app.properties.show');
    Route::get('properties/{property}/spaces/{space}/edit', [PropertyController::class, 'editSpace'])->name('app.spaces.edit');
    Route::middleware('throttle:customer-sensitive')->group(function () {
        Route::post('studio/commit', [WorkspaceStudioController::class, 'save'])->name('app.studio.commit');
        Route::post('studio/new', [WorkspaceStudioController::class, 'restart'])->name('app.studio.new');
        Route::patch('workspace-settings', [WorkspaceSettingsController::class, 'update'])->name('app.workspace.update');
        Route::post('groups', [WorkspaceSettingsController::class, 'group'])->name('app.groups.store');
        Route::patch('groups/{group}', [WorkspaceSettingsController::class, 'group'])->name('app.groups.update');
        Route::post('legal-entities', [WorkspaceSettingsController::class, 'entity'])->name('app.entities.store');
        Route::patch('legal-entities/{entity}', [WorkspaceSettingsController::class, 'entity'])->name('app.entities.update');
        Route::put('members/{membership}/property-access', [WorkspaceSettingsController::class, 'access'])->name('app.property-access.update');
        Route::post('properties', [PropertyController::class, 'store'])->name('app.properties.store');
        Route::patch('properties/{property}', [PropertyController::class, 'update'])->name('app.properties.update');
        Route::post('properties/{property}/buildings', [PropertyController::class, 'building'])->name('app.buildings.store');
        Route::patch('properties/{property}/buildings/{building}', [PropertyController::class, 'building'])->name('app.buildings.update');
        Route::post('properties/{property}/buildings/{building}/floors', [PropertyController::class, 'floor'])->name('app.floors.store');
        Route::patch('properties/{property}/buildings/{building}/floors/{floor}', [PropertyController::class, 'floor'])->name('app.floors.update');
        Route::post('properties/{property}/spaces', [PropertyController::class, 'space'])->name('app.spaces.store');
        Route::post('properties/{property}/spaces/{space}/studio', [PropertyController::class, 'assignStudio'])->name('app.spaces.studio');
        Route::patch('properties/{property}/spaces/{space}', [PropertyController::class, 'space'])->name('app.spaces.update');
    });
});
