<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApplicationSettings\DeleteApplicationLogo;
use App\Actions\ApplicationSettings\UpdateApplicationLogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateApplicationFullLogoRequest;
use App\Http\Requests\Settings\UpdateApplicationLogoRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationLogoController extends Controller
{
    public function __construct(
        private UpdateApplicationLogo $updateApplicationLogo,
        private DeleteApplicationLogo $deleteApplicationLogo,
    ) {}

    public function edit(): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        return Inertia::render('settings/ApplicationLogo', [
            'iconUrl' => ApplicationSetting::iconUrl(),
            'fullLogoUrl' => ApplicationSetting::fullLogoUrl(),
        ]);
    }

    public function update(UpdateApplicationLogoRequest $request): RedirectResponse
    {
        $this->updateApplicationLogo->handle(
            $request->logo(),
            ApplicationSetting::ICON_PATH,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Application icon updated.'),
        ]);

        return to_route('application-logo.edit');
    }

    public function destroy(): RedirectResponse
    {
        Gate::authorize('update', ApplicationSetting::class);

        $this->deleteApplicationLogo->handle(ApplicationSetting::ICON_PATH);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Default application icon restored.'),
        ]);

        return to_route('application-logo.edit');
    }

    public function updateFullLogo(UpdateApplicationFullLogoRequest $request): RedirectResponse
    {
        $this->updateApplicationLogo->handle(
            $request->fullLogo(),
            ApplicationSetting::FULL_LOGO_PATH,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Full application logo updated.'),
        ]);

        return to_route('application-logo.edit');
    }

    public function destroyFullLogo(): RedirectResponse
    {
        Gate::authorize('update', ApplicationSetting::class);

        $this->deleteApplicationLogo->handle(ApplicationSetting::FULL_LOGO_PATH);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Full application logo removed.'),
        ]);

        return to_route('application-logo.edit');
    }
}
