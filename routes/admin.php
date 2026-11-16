<?php

use App\Http\Controllers\Admin\AuthenticationController;
use App\Http\Controllers\Admin\PasskeyController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthenticationController::class, 'show'])->name('login');
    Route::post('login', [AuthenticationController::class, 'store'])->middleware('throttle:platform-login')->name('login.store');
    Route::post('passkeys/options', [PasskeyController::class, 'authenticationOptions'])->middleware('throttle:platform-passkey')->name('passkeys.options');
    Route::post('passkeys/login', [PasskeyController::class, 'authenticate'])->middleware('throttle:platform-passkey')->name('passkeys.login');

    Route::middleware('platform.admin')->group(function () {
        Route::redirect('/', '/admin/settings');
        Route::get('settings', [SettingsController::class, 'show'])->name('settings');
        Route::post('logout', [AuthenticationController::class, 'destroy'])->name('logout');
        Route::post('confirm-password', [AuthenticationController::class, 'confirm'])->middleware('throttle:platform-login')->name('confirm');
        Route::middleware(['platform.recent', 'throttle:platform-sensitive'])->group(function () {
            Route::put('settings/maintenance', [SettingsController::class, 'updateMaintenance'])->name('maintenance.update');
            Route::put('settings/evidence', [SettingsController::class, 'updateEvidence'])->name('evidence.update');
            Route::put('settings/gnosix', [SettingsController::class, 'updateGnosix'])->name('gnosix.update');
            Route::put('settings/smtp', [SettingsController::class, 'updateSmtp'])->name('smtp.update');
            Route::post('settings/smtp/test', [SettingsController::class, 'testSmtp'])->middleware('throttle:platform-test-mail')->name('smtp.test');
            Route::post('passkeys/register-options', [PasskeyController::class, 'registrationOptions'])->name('passkeys.register-options');
            Route::post('passkeys', [PasskeyController::class, 'register'])->name('passkeys.store');
            Route::delete('passkeys/{passkey}', [PasskeyController::class, 'destroy'])->whereNumber('passkey')->name('passkeys.destroy');
        });
    });
});
