<?php

use App\Actions\ApplicationSettings\SeoSettings;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BulkPageController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaFileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageAttachmentController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PageFieldSetController;
use App\Http\Controllers\PageTemplateController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TableExportController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureFeatureEnabled;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (SeoSettings $seo) {
    if (! config('starter.features.public_site')) {
        return to_route('dashboard');
    }
    $seo->apply();

    return Inertia::render('Welcome');
})->name('home');
Route::get('content/{page:slug}', PublicPageController::class)
    ->middleware([EnsureFeatureEnabled::class.':starter.features.public_site', EnsureFeatureEnabled::class.':starter.features.pages'])
    ->name('content.show');
Route::get('sitemap.xml', SitemapController::class)
    ->middleware(EnsureFeatureEnabled::class.':starter.features.public_site')->name('sitemap');
Route::get('robots.txt', RobotsController::class)->name('robots');

Route::middleware(EnsureFeatureEnabled::class.':starter.features.media')->group(function () {
    Route::get('files/{media}/download', [MediaFileController::class, 'download'])->name('media-files.download');
    Route::get('files/{media}/thumbnail', [MediaFileController::class, 'thumbnail'])->name('media-files.thumbnail');
});

Route::withHead(robots: 'none')->middleware(['auth', 'verified'])->group(function () {
    Route::get('pages/{page}/preview', [PublicPageController::class, 'preview'])
        ->middleware([EnsureFeatureEnabled::class.':starter.features.pages', EnsureFeatureEnabled::class.':starter.features.public_site', 'cache.headers:private;no_store'])
        ->name('content.preview');
    Route::middleware(EnsureFeatureEnabled::class.':starter.features.public_site')->group(function () {
        Route::get('seo/{target?}', [SeoController::class, 'edit'])->where('target', 'defaults|home|[0-9]+')->name('seo.edit')->withHead(title: 'Référencement SEO');
        Route::put('seo/{target}', [SeoController::class, 'update'])->where('target', 'defaults|home|[0-9]+')->name('seo.update');
    });
    Route::inertia('dashboard', 'Dashboard')
        ->name('dashboard')
        ->withHead(title: 'Tableau de bord');
    Route::middleware(EnsureFeatureEnabled::class.':starter.features.media')->group(function () {
        Route::resource('media', MediaController::class)->parameters(['media' => 'media'])->only(['index', 'update', 'destroy']);
        Route::post('media', [MediaController::class, 'store'])->middleware('throttle:30,1')->name('media.store');
        Route::post('pages/{page}/attachments', [PageAttachmentController::class, 'store'])
            ->middleware(EnsureFeatureEnabled::class.':starter.features.pages')->name('page-attachments.store');
        Route::delete('pages/{page}/attachments/{media}', [PageAttachmentController::class, 'destroy'])
            ->middleware(EnsureFeatureEnabled::class.':starter.features.pages')->name('page-attachments.destroy');
    });

    Route::get('pages-export', [TableExportController::class, 'pages'])
        ->middleware([EnsureFeatureEnabled::class.':starter.features.pages', 'throttle:10,1'])->name('table-exports.pages');
    Route::get('users-export', [TableExportController::class, 'users'])->middleware('throttle:10,1')->name('table-exports.users');
    Route::get('roles-export', [TableExportController::class, 'roles'])->middleware('throttle:10,1')->name('table-exports.roles');
    Route::patch('pages-bulk', BulkPageController::class)
        ->middleware(EnsureFeatureEnabled::class.':starter.features.pages')->name('pages.bulk');

    Route::middleware(EnsureFeatureEnabled::class.':starter.features.pages')->group(function () {
        Route::resource('page-templates', PageTemplateController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('page-field-sets', PageFieldSetController::class)->only(['store', 'update', 'destroy']);
    });

    Route::resource('pages', PageController::class)
        ->middleware(EnsureFeatureEnabled::class.':starter.features.pages');

    Route::prefix('{resource}-import')->where(['resource' => '[a-z][a-z0-9-]*'])->name('csv-imports.')->group(function () {
        Route::get('/', [CsvImportController::class, 'create'])->name('create');
        Route::post('/', [CsvImportController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('{csvImport}', [CsvImportController::class, 'show'])->name('show');
        Route::patch('{csvImport}', [CsvImportController::class, 'preview'])->middleware('throttle:20,1')->name('preview');
        Route::post('{csvImport}/commit', [CsvImportController::class, 'commit'])->middleware('throttle:10,1')->name('commit');
        Route::get('{csvImport}/errors', [CsvImportController::class, 'errors'])->name('errors');
        Route::delete('{csvImport}', [CsvImportController::class, 'destroy'])->name('destroy');
    });

    /** @var array<string, array{controller: class-string, model: class-string, label: string, enabled: bool}> $resources */
    $resources = config('resources', []);

    foreach ($resources as $name => $resource) {
        Route::resource($name, $resource['controller'])
            ->middleware(EnsureFeatureEnabled::class.':resources.'.$name.'.enabled');
    }
    Route::get('activity', [ActivityController::class, 'index'])
        ->middleware(EnsureFeatureEnabled::class.':starter.features.activity')->name('activity.index');

    Route::middleware(EnsureFeatureEnabled::class.':starter.features.notifications')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}', [NotificationController::class, 'update'])->whereUuid('notification')->name('notifications.update');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->whereUuid('notification')->name('notifications.destroy');
        Route::get('settings/notifications', [NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::put('settings/notifications', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    });

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
