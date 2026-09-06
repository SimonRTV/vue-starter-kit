<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApplicationSettings\GeneralSettings;
use App\Actions\ApplicationSettings\UpdateGeneralSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller
{
    public function edit(GeneralSettings $settings): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        return Inertia::render('settings/General', ['settings' => $settings->get()]);
    }

    public function update(UpdateGeneralSettingsRequest $request, UpdateGeneralSettings $update): RedirectResponse
    {
        $update->handle($request->settings());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les informations générales ont été enregistrées.']);

        return to_route('general-settings.edit');
    }
}
