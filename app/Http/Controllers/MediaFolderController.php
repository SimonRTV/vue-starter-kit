<?php

namespace App\Http\Controllers;

use App\Actions\Media\DeleteMediaFolder;
use App\Actions\Media\SaveMediaFolder;
use App\Http\Requests\SaveMediaFolderRequest;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MediaFolderController extends Controller
{
    public function store(SaveMediaFolderRequest $request, SaveMediaFolder $save): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $folder = $save->handle($user, null, (string) $request->validated('name'), $request->parentId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le dossier « '.$folder->name.' » a été créé.']);

        return back();
    }

    public function update(SaveMediaFolderRequest $request, MediaFolder $folder, SaveMediaFolder $save): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $save->handle($user, $folder, (string) $request->validated('name'), $request->parentId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le dossier a été mis à jour.']);

        return back();
    }

    public function destroy(MediaFolder $folder, DeleteMediaFolder $delete): RedirectResponse
    {
        Gate::authorize('delete', $folder);
        $delete->handle($folder);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le dossier a été supprimé. Son contenu a été déplacé dans le dossier parent.']);

        return back();
    }
}
