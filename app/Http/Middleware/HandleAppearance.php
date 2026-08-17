<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * @var list<string>
     */
    private const array APPEARANCES = ['light', 'dark', 'system'];

    /**
     * @var list<string>
     */
    private const array ADMIN_THEMES = ['neutral', 'ocean', 'forest'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $usesFrontendAppearance = ! $user instanceof User
            || $request->routeIs('home', 'content.*');

        if ($usesFrontendAppearance) {
            $appearance = $request->cookie('frontend_appearance');
            $appearance = in_array($appearance, self::APPEARANCES, true)
                ? $appearance
                : 'system';
            $adminTheme = 'neutral';
        } else {
            $appearance = in_array($user->appearance, self::APPEARANCES, true)
                ? $user->appearance
                : 'system';
            $adminTheme = in_array($user->admin_theme, self::ADMIN_THEMES, true)
                ? $user->admin_theme
                : 'neutral';
        }

        View::share([
            'appearance' => $appearance,
            'appearanceSurface' => $usesFrontendAppearance ? 'frontend' : 'dashboard',
            'adminTheme' => $adminTheme,
        ]);

        return $next($request);
    }
}
