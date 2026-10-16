<?php

namespace App\Actions\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class StoreMediaFile
{
    public function __construct(private CreateThumbnail $thumbnails, private OptimizeImage $optimizer) {}

    /**
     * Store an upload and its thumbnail inside the media directory.
     *
     * @return array{path: string, thumbnail_path: string|null, original_name: string, mime_type: string, size: int}
     */
    public function handle(UploadedFile $upload, string $disk, string $directory, bool $optimize = false, string $suffix = ''): array
    {
        $file = $optimize ? $this->optimizer->handle($upload) : $upload;
        try {
            $path = $file->storeAs($directory, 'original'.$suffix.'.'.$file->extension(), ['disk' => $disk, 'visibility' => 'private']);
            if ($path === false) {
                throw new RuntimeException('Unable to save the file.');
            }
            $thumbnail = $this->thumbnails->handle($file, $disk, $directory, 'thumbnail'.$suffix.'.jpg');
            $originalName = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
            $originalName = Str::replaceMatches('/[\x00-\x1F\x7F]/u', '', $originalName);

            return [
                'path' => $path, 'thumbnail_path' => $thumbnail,
                'original_name' => Str::limit(pathinfo($originalName, PATHINFO_FILENAME), 180, '').'.'.$file->extension(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => (int) $file->getSize(),
            ];
        } finally {
            if ($file !== $upload) {
                @unlink($file->getPathname());
            }
        }
    }
}
