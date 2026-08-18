<?php

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\ApplicationLogoController;
use App\Http\Controllers\Settings\FrontendNavigationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SidebarFooterLinkController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::withHead(robots: 'none')->middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit')
        ->withHead(title: 'Paramètres du profil');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::withHead(robots: 'none')->middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit')
        ->withHead(title: 'Paramètres de sécurité');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])
        ->name('appearance.edit')
        ->withHead(title: 'Paramètres d’apparence');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])
        ->name('appearance.update');

    Route::get('settings/application-logo', [ApplicationLogoController::class, 'edit'])
        ->name('application-logo.edit')
        ->withHead(title: 'Paramètres de l’application');
    Route::post('settings/application-logo', [ApplicationLogoController::class, 'update'])
        ->name('application-logo.update');
    Route::delete('settings/application-logo', [ApplicationLogoController::class, 'destroy'])
        ->name('application-logo.destroy');
    Route::post('settings/application-logo/full', [ApplicationLogoController::class, 'updateFullLogo'])
        ->name('application-logo.full.update');
    Route::delete('settings/application-logo/full', [ApplicationLogoController::class, 'destroyFullLogo'])
        ->name('application-logo.full.destroy');

    Route::get('settings/sidebar-menu', [SidebarFooterLinkController::class, 'edit'])
        ->name('sidebar-footer-links.edit')
        ->withHead(title: 'Menu latéral');
    Route::put('settings/sidebar-menu', [SidebarFooterLinkController::class, 'update'])
        ->name('sidebar-footer-links.update');

    Route::get('settings/frontend-navigation', [FrontendNavigationController::class, 'edit'])
        ->name('frontend-navigation.edit')
        ->withHead(title: 'Navigation publique');
    Route::put('settings/frontend-navigation', [FrontendNavigationController::class, 'update'])
        ->name('frontend-navigation.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
