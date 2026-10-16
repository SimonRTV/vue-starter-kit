<?php

namespace App\Actions\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CreateThumbnail
{
    public function __construct(private DecodeImage $decoder) {}

    public function handle(UploadedFile $file, string $disk, string $directory, string $name = 'thumbnail.jpg'): ?string
    {
        if (! extension_loaded('gd') || ! in_array($file->getMimeType(), config('media.image_mimes'), true)) {
            return null;
        }
        $source = $this->decoder->handle($file);
        $scale = min(1, (int) config('media.thumbnail_size') / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);
        if ($thumbnail === false) {
            throw new RuntimeException('Unable to create a thumbnail.');
        }
        imagefill($thumbnail, 0, 0, 0xFFFFFF);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        try {
            imagejpeg($thumbnail, null, 85);
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $path = $directory.'/'.$name;
        if ($contents === false || ! Storage::disk($disk)->put($path, $contents, 'private')) {
            throw new RuntimeException('Unable to save the thumbnail.');
        }

        return $path;
    }
}
