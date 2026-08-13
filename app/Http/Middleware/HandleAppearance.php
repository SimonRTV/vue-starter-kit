<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
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
        $adminTheme = $request->cookie('admin_theme');

        View::share([
            'appearance' => $request->cookie('appearance') ?? 'system',
            'adminTheme' => in_array($adminTheme, self::ADMIN_THEMES, true)
                ? $adminTheme
                : 'neutral',
        ]);

        return $next($request);
    }
}
