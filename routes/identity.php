<?php

use App\Http\Controllers\Identity\AccountController;
use App\Http\Controllers\Identity\AuthenticationController;
use App\Http\Controllers\Identity\PasswordController;
use App\Http\Controllers\Identity\VerificationController;
use App\Http\Controllers\Identity\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->middleware('customer.locale')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('login', [AuthenticationController::class, 'login'])->name('login');
        Route::post('login', [AuthenticationController::class, 'authenticate'])->middleware('throttle:customer-login')->name('login.store');
        Route::get('register', [AuthenticationController::class, 'register'])->name('register');
        Route::post('register', [AuthenticationController::class, 'store'])->middleware('throttle:customer-register')->name('register.store');
        Route::get('forgot-password', [PasswordController::class, 'forgot'])->name('password.request');
        Route::post('forgot-password', [PasswordController::class, 'send'])->middleware('throttle:customer-reset')->name('password.email');
        Route::get('reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [PasswordController::class, 'update'])->middleware('throttle:customer-reset')->name('password.update');
    });
    Route::middleware(['auth:web', 'customer.session'])->group(function () {
        Route::post('logout', [AuthenticationController::class, 'destroy'])->name('logout');
        Route::get('verify-email', [VerificationController::class, 'show'])->name('verification.notice');
        Route::get('verify-email/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed:relative', 'throttle:customer-sensitive'])->name('verification.verify');
        Route::post('verification-notification', [VerificationController::class, 'send'])->middleware('throttle:customer-verification')->name('verification.send');
        Route::middleware(['verified', 'customer.organization'])->group(function () {
            require __DIR__.'/organizations.php';
            Route::get('onboarding', [WorkspaceController::class, 'onboarding'])->name('app.onboarding');
            Route::post('onboarding', [WorkspaceController::class, 'complete'])->middleware('throttle:customer-sensitive')->name('app.onboarding.complete');
            Route::get('account', [AccountController::class, 'show'])->name('app.account');
            Route::middleware('throttle:customer-sensitive')->group(function () {
                Route::patch('account', [AccountController::class, 'update'])->name('app.account.update');
                Route::put('account/password', [AccountController::class, 'password'])->name('app.password.update');
                Route::delete('account/sessions', [AccountController::class, 'sessions'])->name('app.sessions.destroy');
            });
            Route::get('/', [WorkspaceController::class, 'dashboard'])->middleware('customer.onboarded')->name('app.dashboard');
        });
    });
});
