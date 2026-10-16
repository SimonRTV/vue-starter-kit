<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadMedia
{
    public function __construct(private StoreMediaFile $files) {}

    /** @param list<string> $tags */
    public function handle(User $user, UploadedFile $file, ?string $title = null, ?string $altText = null, string $visibility = 'private', array $tags = [], bool $optimize = false, ?int $folderId = null): Media
    {
        $id = (string) Str::uuid();
        $disk = (string) config('media.disk');
        try {
            $stored = $this->files->handle($file, $disk, $id, $optimize);

            return DB::transaction(fn (): Media => Media::create([
                ...$stored,
                'id' => $id, 'folder_id' => $folderId, 'uploaded_by' => $user->id,
                'title' => filled($title) ? $title : $stored['original_name'],
                'disk' => $disk, 'alt_text' => $altText, 'visibility' => $visibility,
                'tags' => $tags === [] ? null : $tags,
            ]));
        } catch (Throwable $exception) {
            Storage::disk($disk)->deleteDirectory($id);

            throw $exception;
        }
    }
}
