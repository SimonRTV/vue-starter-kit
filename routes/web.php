<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
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
