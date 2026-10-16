<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\MediaFolder;
use Illuminate\Support\Facades\DB;

class DeleteMediaFolder
{
    /**
     * Delete a folder and move its files and subfolders up to its parent, renaming subfolders whose name is taken there.
     */
    public function handle(MediaFolder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            $tree = new MediaFolderTree(MediaFolder::query()->lockForUpdate()->get(['id', 'parent_id', 'name']));
            $folder = MediaFolder::query()->findOrFail($folder->id);
            $taken = $tree->siblingNames($folder->parent_id, $folder->id);
            foreach ($folder->children()->orderBy('name')->get() as $child) {
                $name = $child->name;
                for ($copy = 2; in_array(mb_strtolower($name), $taken, true); $copy++) {
                    $name = mb_substr($child->name, 0, 74).' ('.$copy.')';
                }
                $taken[] = mb_strtolower($name);
                $child->update(['name' => $name, 'parent_id' => $folder->parent_id]);
            }
            Media::query()->where('folder_id', $folder->id)->lazyById()->each(fn (Media $media): bool => $media->update(['folder_id' => $folder->parent_id]));
            $folder->delete();
        });
    }
}
