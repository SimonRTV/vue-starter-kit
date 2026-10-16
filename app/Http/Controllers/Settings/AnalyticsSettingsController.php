<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApplicationSettings\AnalyticsSettings;
use App\Actions\ApplicationSettings\UpdateAnalyticsSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAnalyticsSettingsRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsSettingsController extends Controller
{
    public function edit(AnalyticsSettings $settings): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        return Inertia::render('settings/Analytics', ['settings' => $settings->get()]);
    }

    public function update(UpdateAnalyticsSettingsRequest $request, UpdateAnalyticsSettings $update): RedirectResponse
    {
        $update->handle($request->settings());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les paramètres Google Analytics ont été enregistrés.']);

        return to_route('analytics-settings.edit');
    }
}
