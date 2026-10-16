<?php

namespace App\Http\Controllers;

use App\Actions\Media\DeleteMedia;
use App\Actions\Media\MediaFolderTree;
use App\Actions\Media\MediaLibrary;
use App\Actions\Media\ReplaceMediaFile;
use App\Actions\Media\UploadMedia;
use App\Http\Requests\IndexMediaRequest;
use App\Http\Requests\ReplaceMediaFileRequest;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaRequest;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $filters = $request->filters();
        $media = $library->search($user, $filters)->through(fn (Media $item): array => $library->item($item));
        if ($this->wantsJson($request)) {
            return response()->json($media);
        }
        Head::title('Médiathèque');

        return Inertia::render('media/Index', [
            'media' => $media, 'filters' => $filters,
            'tags' => $library->tags($user), 'stats' => $library->stats($user),
            'folders' => (new MediaFolderTree)->flatten($user), 'maxFolderDepth' => MediaFolder::MAX_DEPTH,
            'canManageFolders' => $user->can('create', MediaFolder::class),
            'canUpload' => $user->can('create', Media::class), 'canPublish' => $user->can(MediaPolicy::PUBLISH),
            'canBulkUpdate' => $user->can(MediaPolicy::UPDATE) || $user->can(MediaPolicy::DELETE),
            'maxUploadKb' => config('media.max_upload_kb'),
            'extensions' => config('media.extensions'),
            'perPageOptions' => config('media.per_page_options'),
            'maxTags' => config('media.max_tags'),
        ]);
    }

    public function store(StoreMediaRequest $request, UploadMedia $upload, MediaLibrary $library): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var list<string> $tags */
        $tags = $request->validated('tags', []);
        $media = $upload->handle($user, $request->upload(), $request->validated('title'), $request->validated('alt_text'),
            (string) $request->validated('visibility', 'private'), $tags, $request->boolean('optimize'),
            $request->validated('folder_id') === null ? null : (int) $request->validated('folder_id'));
        if ($this->wantsJson($request)) {
            return response()->json(['media' => $library->item($media->load(['uploader:id,name', 'pages']))], 201);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le fichier a été importé.']);

        return to_route('media.index');
    }

    public function update(UpdateMediaRequest $request, Media $media): RedirectResponse
    {
        $attributes = $request->safe()->only(['title', 'alt_text', 'visibility']);
        if ($request->exists('folder_id')) {
            $folderId = $request->validated('folder_id');
            $attributes['folder_id'] = $folderId === null ? null : (int) $folderId;
        }
        if ($request->has('tags')) {
            /** @var list<string> $tags */
            $tags = $request->validated('tags', []);
            $attributes['tags'] = $tags === [] ? null : $tags;
        }
        $media->update($attributes);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le fichier a été mis à jour.']);

        return back();
    }

    public function replace(ReplaceMediaFileRequest $request, Media $media, ReplaceMediaFile $replace): RedirectResponse
    {
        $replace->handle($media, $request->upload(), $request->boolean('optimize'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le fichier a été remplacé. Les liens existants restent valides.']);

        return back();
    }

    public function destroy(Media $media, DeleteMedia $delete): RedirectResponse
    {
        Gate::authorize('delete', $media);
        $delete->handle($media);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le fichier a été supprimé.']);

        return back();
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() && ! $request->header('X-Inertia');
    }
}
