<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApplicationSettings\DeleteFavicons;
use App\Actions\ApplicationSettings\DeleteFaviconSvg;
use App\Actions\ApplicationSettings\FaviconSettings;
use App\Actions\ApplicationSettings\GeneralSettings;
use App\Actions\ApplicationSettings\UpdateFaviconColors;
use App\Actions\ApplicationSettings\UpdateFavicons;
use App\Actions\ApplicationSettings\UpdateFaviconSvg;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFaviconColorsRequest;
use App\Http\Requests\Settings\UpdateFaviconsRequest;
use App\Http\Requests\Settings\UpdateFaviconSvgRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FaviconController extends Controller
{
    public function edit(FaviconSettings $faviconSettings, GeneralSettings $generalSettings): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        $settings = $faviconSettings->get();

        return Inertia::render('settings/Favicons', [
            'icons' => $faviconSettings->icons(),
            'colors' => [
                'theme_color' => $settings['theme_color'],
                'background_color' => $settings['background_color'],
            ],
            'applicationName' => $generalSettings->get()['name'],
        ]);
    }

    public function update(UpdateFaviconsRequest $request, UpdateFavicons $updateFavicons): RedirectResponse
    {
        $updateFavicons->handle($request->favicon());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les favicons ont été générés.']);

        return to_route('favicons.edit');
    }

    public function updateColors(UpdateFaviconColorsRequest $request, UpdateFaviconColors $updateFaviconColors): RedirectResponse
    {
        $updateFaviconColors->handle($request->colors());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les couleurs ont été enregistrées.']);

        return to_route('favicons.edit');
    }

    public function destroy(DeleteFavicons $deleteFavicons): RedirectResponse
    {
        Gate::authorize('update', ApplicationSetting::class);

        $deleteFavicons->handle();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les favicons par défaut ont été restaurés.']);

        return to_route('favicons.edit');
    }

    public function updateSvg(UpdateFaviconSvgRequest $request, UpdateFaviconSvg $updateFaviconSvg): RedirectResponse
    {
        $updateFaviconSvg->handle($request->svg());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le favicon SVG a été enregistré.']);

        return to_route('favicons.edit');
    }

    public function destroySvg(DeleteFaviconSvg $deleteFaviconSvg): RedirectResponse
    {
        Gate::authorize('update', ApplicationSetting::class);

        $deleteFaviconSvg->handle();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le favicon SVG a été supprimé.']);

        return to_route('favicons.edit');
    }
}
