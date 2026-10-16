<?php

use App\Http\Controllers\Settings\AnalyticsSettingsController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\ApplicationLogoController;
use App\Http\Controllers\Settings\FaviconController;
use App\Http\Controllers\Settings\FrontendAppearanceController;
use App\Http\Controllers\Settings\FrontendNavigationController;
use App\Http\Controllers\Settings\GeneralSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SidebarFooterLinkController;
use App\Http\Middleware\EnsureFeatureEnabled;
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

    Route::get('settings/general', [GeneralSettingsController::class, 'edit'])
        ->name('general-settings.edit')
        ->withHead(title: 'Informations générales');
    Route::put('settings/general', [GeneralSettingsController::class, 'update'])
        ->name('general-settings.update');

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
    Route::post('settings/application-logo/full/dark', [ApplicationLogoController::class, 'updateDarkFullLogo'])
        ->name('application-logo.full.dark.update');
    Route::delete('settings/application-logo/full/dark', [ApplicationLogoController::class, 'destroyDarkFullLogo'])
        ->name('application-logo.full.dark.destroy');

    Route::get('settings/favicons', [FaviconController::class, 'edit'])
        ->name('favicons.edit')
        ->withHead(title: 'Favicons');
    Route::post('settings/favicons', [FaviconController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('favicons.update');
    Route::put('settings/favicons/colors', [FaviconController::class, 'updateColors'])
        ->name('favicons.colors.update');
    Route::delete('settings/favicons', [FaviconController::class, 'destroy'])
        ->name('favicons.destroy');
    Route::post('settings/favicons/svg', [FaviconController::class, 'updateSvg'])
        ->middleware('throttle:30,1')
        ->name('favicons.svg.update');
    Route::delete('settings/favicons/svg', [FaviconController::class, 'destroySvg'])
        ->name('favicons.svg.destroy');

    Route::get('settings/sidebar-menu', [SidebarFooterLinkController::class, 'edit'])
        ->name('sidebar-footer-links.edit')
        ->withHead(title: 'Menu latéral');
    Route::put('settings/sidebar-menu', [SidebarFooterLinkController::class, 'update'])
        ->name('sidebar-footer-links.update');

    Route::get('settings/frontend-navigation', [FrontendNavigationController::class, 'edit'])
        ->middleware(EnsureFeatureEnabled::class.':starter.features.public_site')
        ->name('frontend-navigation.edit')
        ->withHead(title: 'Navigation publique');
    Route::put('settings/frontend-navigation', [FrontendNavigationController::class, 'update'])
        ->middleware(EnsureFeatureEnabled::class.':starter.features.public_site')
        ->name('frontend-navigation.update');

    Route::get('settings/frontend-appearance', [FrontendAppearanceController::class, 'edit'])
        ->middleware(EnsureFeatureEnabled::class.':starter.features.public_site')
        ->name('frontend-appearance.edit')
        ->withHead(title: 'Apparence du site public');
    Route::put('settings/frontend-appearance', [FrontendAppearanceController::class, 'update'])
        ->middleware(EnsureFeatureEnabled::class.':starter.features.public_site')
        ->name('frontend-appearance.update');

    Route::get('settings/analytics', [AnalyticsSettingsController::class, 'edit'])
        ->middleware(EnsureFeatureEnabled::class.':starter.features.public_site')
        ->name('analytics-settings.edit')
        ->withHead(title: 'Google Analytics');
    Route::put('settings/analytics', [AnalyticsSettingsController::class, 'update'])
        ->middleware(EnsureFeatureEnabled::class.':starter.features.public_site')
        ->name('analytics-settings.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
