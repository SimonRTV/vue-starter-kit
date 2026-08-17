<?php

namespace App\Providers;

use App\Policies\RolePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Head\Enums\ImageType;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Enums\TwitterCard;
use Laravel\Head\ErrorPages;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        $this->configureHead();
        $this->configureDefaults();
    }

    /**
     * Configure document metadata shared by the Inertia frontend.
     */
    protected function configureHead(): void
    {
        $appName = (string) config('app.name', 'Laravel');

        Head::defaults(fn (HeadBuilder $head) => $head
            ->title($appName, suffix: " - {$appName}")
            ->canonical(forceHttps: app()->isProduction())
            ->og(
                type: OgType::Website,
                siteName: $appName,
                locale: app()->getLocale(),
            )
            ->twitter(card: TwitterCard::Summary));

        Head::inertiaGlobals(fn (HeadBuilder $head) => $head
            ->viewport('width=device-width, initial-scale=1')
            ->themeColor('#ff2d20')
            ->applicationName($appName)
            ->colorScheme('light dark')
            ->appleWebAppTitle($appName)
            ->favicon(asset('favicon.svg'), type: ImageType::Svg, sizes: 'any')
            ->favicon(asset('favicon-96x96.png'), type: ImageType::Png, sizes: '96x96')
            ->link('shortcut icon', asset('favicon.ico'), ['type' => ImageType::Ico->value])
            ->appleTouchIcon(asset('apple-touch-icon.png'), sizes: '180x180')
            ->manifest(asset('site.webmanifest')));

        Head::errors(function (ErrorPages $errors): void {
            $errors->defaults(robots: 'none');
            $errors->status(403, title: 'Accès interdit');
            $errors->status(404, title: 'Page introuvable');
            $errors->status(419, title: 'Page expirée');
            $errors->status(429, title: 'Trop de requêtes');
            $errors->status(500, title: 'Erreur serveur');
            $errors->status(503, title: 'Service indisponible');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
