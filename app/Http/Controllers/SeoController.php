<?php

namespace App\Http\Controllers;

use App\Actions\ApplicationSettings\DeleteSeoImage;
use App\Actions\ApplicationSettings\SeoSettings;
use App\Actions\ApplicationSettings\UpdateSeoImage;
use App\Actions\ApplicationSettings\UpdateSeoSettings;
use App\Http\Requests\Settings\UpdateSeoImageRequest;
use App\Http\Requests\Settings\UpdateSeoSettingsRequest;
use App\Models\ApplicationSetting;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeoController extends Controller
{
    public function edit(SeoSettings $seo, string $target = 'defaults'): Response
    {
        Gate::authorize('viewAny', ApplicationSetting::class);
        $page = $this->page($target);

        return Inertia::render('seo/Edit', [
            'target' => $target,
            'settings' => $target === 'defaults' ? $seo->defaults() : $seo->values($page),
            'defaults' => $seo->defaults(),
            'images' => $seo->uploadedImages($target, $page),
            'defaultImages' => $seo->defaultImages(),
            'fallback' => ['title' => $page?->title, 'description' => $page?->excerpt],
            'publicUrl' => $seo->publicUrl($page),
            'isPublished' => $page === null || ($page->is_published && $page->published_at?->isPast()),
            'pages' => config('starter.features.pages')
                ? Page::query()->orderBy('title')->get(['id', 'title', 'slug', 'is_published', 'published_at']) : [],
        ]);
    }

    public function update(UpdateSeoSettingsRequest $request, UpdateSeoSettings $update, string $target): RedirectResponse
    {
        $update->handle($target, $request->settings(), $this->page($target));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Les paramètres SEO ont été enregistrés.']);

        return to_route('seo.edit', ['target' => $target]);
    }

    public function storeImage(UpdateSeoImageRequest $request, UpdateSeoImage $update, string $target, string $field): RedirectResponse
    {
        $update->handle($target, $field, $request->uploadedImage(), $this->page($target));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'L’image de partage a été téléversée.']);

        return to_route('seo.edit', ['target' => $target]);
    }

    public function destroyImage(DeleteSeoImage $delete, string $target, string $field): RedirectResponse
    {
        Gate::authorize('update', ApplicationSetting::class);
        $delete->handle($target, $field, $this->page($target));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'L’image de partage a été supprimée.']);

        return to_route('seo.edit', ['target' => $target]);
    }

    private function page(string $target): ?Page
    {
        if (in_array($target, ['defaults', 'home'], true)) {
            return null;
        }
        abort_unless(config('starter.features.pages') && ctype_digit($target), 404);

        return Page::query()->findOrFail((int) $target);
    }
}
