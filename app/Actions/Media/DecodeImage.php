<?php

namespace App\Actions\Media;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class DecodeImage
{
    /**
     * Decode an uploaded image and apply its EXIF orientation so re-encoded copies display upright.
     */
    public function handle(UploadedFile $file): GdImage
    {
        $image = @imagecreatefromstring($file->getContent());
        if ($image === false) {
            throw new RuntimeException('Unable to decode the image.');
        }

        return $this->orient($image, $this->orientation($file));
    }

    private function orientation(UploadedFile $file): int
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($file->getPathname());

        return is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            throw new RuntimeException('Unable to rotate the image.');
        }

        return $rotated;
    }
}
