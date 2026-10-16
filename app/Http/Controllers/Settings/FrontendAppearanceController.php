<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApplicationSettings\FrontendAppearanceSettings;
use App\Actions\ApplicationSettings\UpdateFrontendAppearanceSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFrontendAppearanceRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FrontendAppearanceController extends Controller
{
    public function edit(FrontendAppearanceSettings $settings): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        return Inertia::render('settings/FrontendAppearance', ['settings' => $settings->get()]);
    }

    public function update(UpdateFrontendAppearanceRequest $request, UpdateFrontendAppearanceSettings $update): RedirectResponse
    {
        $update->handle($request->settings());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'L’apparence du site public a été enregistrée.']);

        return to_route('frontend-appearance.edit');
    }
}
