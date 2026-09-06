<?php

namespace App\Http\Controllers;

use App\Actions\Media\DeleteMedia;
use App\Actions\Media\MediaLibrary;
use App\Actions\Media\UploadMedia;
use App\Http\Requests\IndexMediaRequest;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Models\Media;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class MediaController extends Controller
{
    public function index(IndexMediaRequest $request, MediaLibrary $library): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $visibility = $request->validated('visibility');
        $media = $library->search($user, $search, is_string($visibility) ? $visibility : null)
            ->through(fn (Media $item): array => $library->item($item));
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($media);
        }
        Head::title('Médiathèque');

        return Inertia::render('media/Index', [
            'media' => $media, 'filters' => ['search' => $search, 'visibility' => $visibility],
            'canUpload' => $user->can('create', Media::class), 'canPublish' => $user->can(MediaPolicy::PUBLISH),
            'maxUploadKb' => config('media.max_upload_kb'),
            'extensions' => config('media.extensions'),
        ]);
    }

    public function store(StoreMediaRequest $request, UploadMedia $upload): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $upload->handle($user, $request->upload(), $request->validated('title'), $request->validated('alt_text'), (string) $request->validated('visibility', 'private'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le fichier a été importé.']);

        return to_route('media.index');
    }

    public function update(UpdateMediaRequest $request, Media $media): RedirectResponse
    {
        $media->update($request->safe()->only(['title', 'alt_text', 'visibility']));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le fichier a été mis à jour.']);

        return to_route('media.index');
    }

    public function destroy(Media $media, DeleteMedia $delete): RedirectResponse
    {
        Gate::authorize('delete', $media);
        $delete->handle($media);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le fichier a été supprimé.']);

        return to_route('media.index');
    }
}
