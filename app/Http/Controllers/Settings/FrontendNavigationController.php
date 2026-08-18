<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApplicationSettings\UpdateFrontendNavigation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFrontendNavigationRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FrontendNavigationController extends Controller
{
    public function __construct(
        private UpdateFrontendNavigation $updateFrontendNavigation,
    ) {}

    public function edit(): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        return Inertia::render('settings/FrontendNavigation', [
            'items' => ApplicationSetting::frontendNavigation(),
        ]);
    }

    public function update(UpdateFrontendNavigationRequest $request): RedirectResponse
    {
        $this->updateFrontendNavigation->handle($request->items());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Public navigation updated.'),
        ]);

        return to_route('frontend-navigation.edit');
    }
}
