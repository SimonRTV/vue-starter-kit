<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApplicationSettings\UpdateSidebarFooterLinks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSidebarFooterLinksRequest;
use App\Models\ApplicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SidebarFooterLinkController extends Controller
{
    public function __construct(
        private UpdateSidebarFooterLinks $updateSidebarFooterLinks,
    ) {}

    public function edit(): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);

        return Inertia::render('settings/SidebarFooterLinks', [
            'links' => ApplicationSetting::sidebarFooterLinks(),
        ]);
    }

    public function update(UpdateSidebarFooterLinksRequest $request): RedirectResponse
    {
        $this->updateSidebarFooterLinks->handle($request->links());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Sidebar footer links updated.'),
        ]);

        return to_route('sidebar-footer-links.edit');
    }
}
