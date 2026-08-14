<?php

namespace App\Http\Middleware;

use App\Models\ApplicationSetting;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Role;

class HandleInertiaRequests extends Middleware
{
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
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'branding' => [
                'iconUrl' => ApplicationSetting::iconUrl(),
                'fullLogoUrl' => ApplicationSetting::fullLogoUrl(),
            ],
            'navigation' => [
                'sidebarFooterLinks' => ApplicationSetting::sidebarFooterLinks(),
            ],
            'auth' => [
                'user' => $user,
                'can' => [
                    'managePages' => $user instanceof User
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
