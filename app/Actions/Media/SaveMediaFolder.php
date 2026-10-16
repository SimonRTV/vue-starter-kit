<?php

namespace App\Actions\Media;

use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMediaFolder
{
    /**
     * Create a folder, or rename and move an existing one, enforcing unique sibling names and the depth limit.
     */
    public function handle(User $user, ?MediaFolder $folder, string $name, ?int $parentId): MediaFolder
    {
        return DB::transaction(function () use ($user, $folder, $name, $parentId): MediaFolder {
            $tree = new MediaFolderTree(MediaFolder::query()->lockForUpdate()->get(['id', 'parent_id', 'name']));
            if ($parentId !== null && $tree->depth($parentId) === 0) {
                throw ValidationException::withMessages(['parent_id' => 'Le dossier parent n’existe plus.']);
            }
            if ($folder !== null && $parentId !== null && ($parentId === $folder->id || in_array($parentId, $tree->descendantIds($folder->id), true))) {
                throw ValidationException::withMessages(['parent_id' => 'Un dossier ne peut pas être déplacé dans lui-même ou dans un de ses sous-dossiers.']);
            }
            $levels = ($parentId === null ? 0 : $tree->depth($parentId)) + ($folder === null ? 1 : $tree->height($folder->id));
            if ($levels > MediaFolder::MAX_DEPTH) {
                throw ValidationException::withMessages(['parent_id' => 'Les dossiers peuvent contenir au maximum '.MediaFolder::MAX_DEPTH.' niveaux.']);
            }
            if (in_array(mb_strtolower($name), $tree->siblingNames($parentId, $folder?->id), true)) {
                throw ValidationException::withMessages(['name' => 'Un dossier porte déjà ce nom à cet emplacement.']);
            }
            if ($folder === null) {
                return MediaFolder::create(['name' => $name, 'parent_id' => $parentId, 'created_by' => $user->id]);
            }
            $folder->update(['name' => $name, 'parent_id' => $parentId]);

            return $folder;
        });
    }
}
