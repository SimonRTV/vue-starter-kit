<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadMedia
{
    public function __construct(private CreateThumbnail $thumbnails) {}

    public function handle(User $user, UploadedFile $file, ?string $title = null, ?string $altText = null, string $visibility = 'private'): Media
    {
        $id = (string) Str::uuid();
        $disk = (string) config('media.disk');
        $path = null;
        try {
            $path = $file->storeAs($id, 'original.'.$file->extension(), ['disk' => $disk, 'visibility' => 'private']);
            if ($path === false) {
                throw new RuntimeException('Unable to save the file.');
            }
            $thumbnail = $this->thumbnails->handle($file, $disk, $id);
            $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            $originalName = Str::replaceMatches('/[\x00-\x1F\x7F]/u', '', $originalName);
            $name = Str::limit(pathinfo($originalName, PATHINFO_FILENAME), 180, '').'.'.$file->extension();

            return DB::transaction(fn (): Media => Media::create([
                'id' => $id, 'uploaded_by' => $user->id,
                'title' => filled($title) ? $title : $name, 'original_name' => $name,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(), 'disk' => $disk, 'path' => $path,
                'thumbnail_path' => $thumbnail, 'alt_text' => $altText, 'visibility' => $visibility,
            ]));
        } catch (Throwable $exception) {
            Storage::disk($disk)->deleteDirectory($id);

            throw $exception;
        }
    }
}
