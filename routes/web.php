<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')
    ->name('home')
    ->withHead(
        title: 'Welcome',
        description: 'A flexible, thoughtful workspace for bringing people, priorities, and progress together.',
        robots: 'all',
    );
Route::get('content/{page:slug}', PublicPageController::class)
    ->name('content.show')
    ->withHead(robots: 'all');

Route::withHead(robots: 'none')->middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')
        ->name('dashboard')
        ->withHead(title: 'Tableau de bord');
    Route::resource('pages', PageController::class);
    Route::resource('roles', RoleController::class);
    Route::post('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
    Route::delete('users/{user}/disable', [UserController::class, 'enable'])->name('users.enable');
    Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
        ->middleware('throttle:3,1')
        ->name('users.password-reset');
    Route::post('users/{user}/security-reset', [UserController::class, 'resetSecurity'])
        ->middleware('throttle:3,1')
        ->name('users.security-reset');
    Route::resource('users', UserController::class);
});

require __DIR__.'/settings.php';
