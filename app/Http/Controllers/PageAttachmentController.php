<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageAttachmentRequest;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PageAttachmentController extends Controller
{
    public function store(StorePageAttachmentRequest $request, Page $page): RedirectResponse
    {
        DB::transaction(function () use ($request, $page): void {
            $page = Page::query()->lockForUpdate()->findOrFail($page->id);
            $media = Media::query()->lockForUpdate()->findOrFail($request->string('media_id')->toString());
            Gate::authorize('view', $media);
            if ($page->attachments()->count() >= 50 && ! $page->attachments()->whereKey($media->id)->exists()) {
                throw ValidationException::withMessages(['media_id' => 'Une page peut contenir au maximum 50 fichiers.']);
            }
            $page->attachments()->syncWithoutDetaching([$media->id]);
        });

        return to_route('pages.edit', $page);
    }

    public function destroy(Page $page, Media $media): RedirectResponse
    {
        Gate::authorize('update', $page);
        abort_unless($page->attachments()->whereKey($media->id)->exists(), 404);
        $page->attachments()->detach($media->id);

        return to_route('pages.edit', $page);
    }
}
