<?php

use App\Http\Controllers\Identity\OrganizationController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/workspace.php';
require __DIR__.'/building-studio.php';
require __DIR__.'/evidence.php';
require __DIR__.'/assets.php';
require __DIR__.'/dna.php';
require __DIR__.'/time-machine.php';
require __DIR__.'/maintenance.php';
require __DIR__.'/operations.php';
require __DIR__.'/trust.php';
require __DIR__.'/field-admin.php';

Route::get('organizations', [OrganizationController::class, 'index'])->name('app.organizations.index');
Route::get('organizations/create', [OrganizationController::class, 'create'])->name('app.organizations.create');
Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('app.organizations.show');
Route::get('organizations/{organization}/modules/{capability}', [OrganizationController::class, 'capability'])->name('app.organizations.module');
Route::post('organization-onboarding/draft', [OrganizationController::class, 'save'])->middleware('throttle:organization-draft')->name('app.organizations.draft');
Route::middleware('throttle:customer-sensitive')->group(function () {
    Route::post('organization-onboarding', [OrganizationController::class, 'store'])->name('app.organizations.store');
    Route::post('organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('app.organizations.switch');
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update'])->name('app.organizations.update');
    Route::put('organizations/{organization}/capabilities', [OrganizationController::class, 'configureCapability'])->name('app.organizations.capabilities');
    Route::post('organizations/{organization}/members', [OrganizationController::class, 'addMember'])->name('app.organizations.members.store');
    Route::patch('organizations/{organization}/members/{membership}', [OrganizationController::class, 'updateMember'])->name('app.organizations.members.update');
    Route::delete('organizations/{organization}/members/{membership}', [OrganizationController::class, 'removeMember'])->name('app.organizations.members.destroy');
});
