<?php

namespace App\Actions\Media;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DeleteMedia
{
    public function handle(Media $media): void
    {
        DB::transaction(function () use ($media): void {
            $locked = Media::query()->lockForUpdate()->findOrFail($media->id);
            if (DB::table('media_attachments')->where('media_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['media' => 'Détachez ce fichier de ses contenus avant de le supprimer.']);
            }
            $paths = array_values(array_filter([$locked->path, $locked->thumbnail_path]));
            if (! Storage::disk($locked->disk)->delete($paths)) {
                throw new RuntimeException('Unable to delete the files.');
            }
            $locked->delete();
        });
    }
}
