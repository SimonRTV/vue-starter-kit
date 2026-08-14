<?php

use App\Http\Controllers\Settings\ApplicationLogoController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SidebarFooterLinkController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/application-logo', [ApplicationLogoController::class, 'edit'])
        ->name('application-logo.edit');
    Route::post('settings/application-logo', [ApplicationLogoController::class, 'update'])
        ->name('application-logo.update');
    Route::delete('settings/application-logo', [ApplicationLogoController::class, 'destroy'])
        ->name('application-logo.destroy');
    Route::post('settings/application-logo/full', [ApplicationLogoController::class, 'updateFullLogo'])
        ->name('application-logo.full.update');
    Route::delete('settings/application-logo/full', [ApplicationLogoController::class, 'destroyFullLogo'])
        ->name('application-logo.full.destroy');

    Route::get('settings/sidebar-menu', [SidebarFooterLinkController::class, 'edit'])
        ->name('sidebar-footer-links.edit');
    Route::put('settings/sidebar-menu', [SidebarFooterLinkController::class, 'update'])
        ->name('sidebar-footer-links.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
