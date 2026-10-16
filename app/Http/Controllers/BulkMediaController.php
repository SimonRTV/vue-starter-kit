<?php

namespace App\Http\Controllers;

use App\Actions\Media\BulkUpdateMedia;
use App\Http\Requests\BulkMediaRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BulkMediaController extends Controller
{
    public function __invoke(BulkMediaRequest $request, BulkUpdateMedia $bulk): RedirectResponse
    {
        /** @var list<string> $ids */
        $ids = $request->validated('ids');
        /** @var 'public'|'private'|'delete'|'move' $action */
        $action = $request->validated('action');
        $folderId = $request->validated('folder_id');
        $bulk->handle($ids, $action, $folderId === null ? null : (int) $folderId);
        Inertia::flash('toast', ['type' => 'success', 'message' => match ($action) {
            'delete' => 'Les fichiers sélectionnés ont été supprimés.',
            'public' => 'Les fichiers sélectionnés sont maintenant publics.',
            'private' => 'Les fichiers sélectionnés sont maintenant privés.',
            'move' => 'Les fichiers sélectionnés ont été déplacés.',
        }]);

        return back();
    }
}
