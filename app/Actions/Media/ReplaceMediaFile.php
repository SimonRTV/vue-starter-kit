<?php

namespace App\Actions\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReplaceMediaFile
{
    public function __construct(private StoreMediaFile $files) {}

    /**
     * Swap the stored file while keeping the media ID, so existing links and attachments keep working.
     */
    public function handle(Media $media, UploadedFile $file, bool $optimize = false): Media
    {
        $stored = $this->files->handle($file, $media->disk, $media->id, $optimize, '-'.Str::lower(Str::random(8)));
        try {
            [$media, $previous] = DB::transaction(function () use ($media, $stored): array {
                $locked = Media::query()->lockForUpdate()->findOrFail($media->id);
                $previous = array_values(array_filter([$locked->path, $locked->thumbnail_path]));
                $locked->update([
                    ...$stored,
                    'title' => $locked->title === $locked->original_name ? $stored['original_name'] : $locked->title,
                ]);

                return [$locked, $previous];
            });
        } catch (Throwable $exception) {
            Storage::disk($media->disk)->delete(array_values(array_filter([$stored['path'], $stored['thumbnail_path']])));

            throw $exception;
        }
        Storage::disk($media->disk)->delete($previous);

        return $media;
    }
}
