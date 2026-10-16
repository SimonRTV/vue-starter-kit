<?php

namespace App\Actions\Media;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class OptimizeImage
{
    public function __construct(private DecodeImage $decoder) {}

    /**
     * Re-encode a JPEG or PNG upload as WebP, keeping the original when the result is not smaller.
     */
    public function handle(UploadedFile $file): UploadedFile
    {
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/png'], true) || ! function_exists('imagewebp')) {
            return $file;
        }
        $image = $this->decoder->handle($file);
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $path = tempnam(sys_get_temp_dir(), 'media-webp-');
        if ($path === false || ! imagewebp($image, $path, (int) config('media.webp_quality'))) {
            throw new RuntimeException('Unable to encode the image.');
        }
        clearstatcache(true, $path);
        $size = filesize($path);
        if ($size === false || $size === 0 || $size >= $file->getSize()) {
            @unlink($path);

            return $file;
        }
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.webp';

        return new UploadedFile($path, $name, 'image/webp', null, true);
    }
}
