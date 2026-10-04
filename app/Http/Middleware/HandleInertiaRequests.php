<?php

namespace App\Http\Middleware;

use App\Actions\ApplicationSettings\GeneralSettings;
use App\Models\Activity;
use App\Models\ApplicationSetting;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;
use Laravel\Head\HeadManager;
use Spatie\Permission\Models\Role;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private GeneralSettings $generalSettings) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        Head::flush();

        $user = $request->user();
        $settings = $this->generalSettings->get();
        Head::defaults(fn (HeadBuilder $head) => $head->title($settings['name'], suffix: ' - '.$settings['name'])->og(siteName: $settings['name']));
        Head::inertiaGlobals(fn (HeadBuilder $head) => $head->applicationName($settings['name'])->appleWebAppTitle($settings['name']));
        $resources = [];
        /** @var array<string, array{model: class-string, label: string, enabled: bool}> $definitions */
        $definitions = config('resources', []);
        foreach ($definitions as $name => $resource) {
            if ($resource['enabled'] && $user instanceof User && $user->can('viewAny', $resource['model'])) {
                $resources[] = ['title' => $resource['label'], 'url' => route($name.'.index')];
            }
        }

        return [
            ...parent::share($request),
            'name' => $settings['name'],
            'application' => $settings,
            'features' => config('starter.features'),
            'notifications' => fn (): array => [
                'unreadCount' => config('starter.features.notifications') && $user instanceof User && $user->hasVerifiedEmail()
                    ? $user->unreadNotifications()->where('type', 'workspace')->count() : 0,
            ],
            HeadManager::INERTIA_PROP => fn (): array => Head::toInertiaElements(),
            'branding' => [
                'iconUrl' => ApplicationSetting::iconUrl(),
                'fullLogoUrl' => ApplicationSetting::fullLogoUrl(),
                'darkFullLogoUrl' => ApplicationSetting::darkFullLogoUrl(),
            ],
            'navigation' => [
                'frontend' => config('starter.features.public_site') ? ApplicationSetting::frontendNavigation() : [],
                'resources' => $resources,
                'sidebarFooterLinks' => ApplicationSetting::sidebarFooterLinks(),
            ],
            'auth' => [
                'user' => $user,
                'can' => [
                    'viewActivity' => config('starter.features.activity') && $user instanceof User && $user->can('viewAny', Activity::class),
                    'manageMedia' => config('starter.features.media') && $user instanceof User && $user->can('viewAny', Media::class),
                    'managePages' => config('starter.features.pages') && $user instanceof User
                        && $user->can('viewAny', Page::class),
                    'manageUsers' => $user instanceof User
                        && $user->can('viewAny', User::class),
                    'manageRoles' => $user instanceof User
                        && $user->can('viewAny', Role::class),
                    'manageApplicationSettings' => $user instanceof User
                        && $user->can('viewAny', ApplicationSetting::class),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
