<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Policies\MediaPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BulkUpdateMedia
{
    public function __construct(private DeleteMedia $delete) {}

    /**
     * Apply a move, visibility change or deletion to every selected file, authorizing all rows before changing any.
     *
     * @param  list<string>  $ids
     * @param  'public'|'private'|'delete'|'move'  $action
     */
    public function handle(array $ids, string $action, ?int $folderId = null): void
    {
        if ($action === 'delete') {
            $this->deleteAll($ids);

            return;
        }

        DB::transaction(function () use ($ids, $action, $folderId): void {
            $media = $this->lock($ids);
            foreach ($media as $item) {
                Gate::authorize('update', $item);
                if ($action === 'public' && $item->visibility !== 'public') {
                    Gate::authorize(MediaPolicy::PUBLISH);
                }
            }
            foreach ($media as $item) {
                $item->update($action === 'move' ? ['folder_id' => $folderId] : ['visibility' => $action]);
            }
        });
    }

    /** @param list<string> $ids */
    private function deleteAll(array $ids): void
    {
        $media = DB::transaction(function () use ($ids): Collection {
            $media = $this->lock($ids);
            foreach ($media as $item) {
                Gate::authorize('delete', $item);
            }
            $attached = DB::table('media_attachments')->whereIn('media_id', $ids)->distinct()->count('media_id');
            if ($attached > 0) {
                throw ValidationException::withMessages(['ids' => $attached === 1
                    ? 'Un fichier sélectionné est encore joint à un contenu. Détachez-le avant de le supprimer.'
                    : $attached.' fichiers sélectionnés sont encore joints à des contenus. Détachez-les avant de les supprimer.']);
            }

            return $media;
        });
        foreach ($media as $item) {
            $this->delete->handle($item);
        }
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Media>
     */
    private function lock(array $ids): Collection
    {
        $media = Media::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
        if ($media->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Un fichier sélectionné n’existe plus. Actualisez la médiathèque.']);
        }

        return $media;
    }
}
