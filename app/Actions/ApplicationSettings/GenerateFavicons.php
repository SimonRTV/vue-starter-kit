<?php

namespace App\Actions\ApplicationSettings;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateFavicons
{
    /** @var list<int> */
    public const ICO_SIZES = [16, 32, 48];

    /**
     * Ratio of the canvas kept free around icons that platforms may crop to a mask.
     */
    private const MASKABLE_PADDING = 0.1;

    /**
     * Render the complete favicon set from a source image into a new public directory.
     *
     * @return string The directory holding the generated files.
     */
    public function handle(string $sourceImage, string $backgroundColor): string
    {
        $source = $this->squareSource($sourceImage);
        $directory = FaviconSettings::DIRECTORY.'/'.Str::lower((string) Str::ulid());
        $disk = Storage::disk('public');

        try {
            $files = [
                FaviconSettings::SOURCE_FILE => $this->png($source, 512),
                FaviconSettings::PNG_FILE => $this->png($source, 96),
                FaviconSettings::ICO_FILE => $this->ico($source),
                FaviconSettings::APPLE_TOUCH_ICON_FILE => $this->png($source, 180, $backgroundColor),
                FaviconSettings::MANIFEST_SMALL_ICON_FILE => $this->png($source, 192, $backgroundColor, self::MASKABLE_PADDING),
                FaviconSettings::MANIFEST_LARGE_ICON_FILE => $this->png($source, 512, $backgroundColor, self::MASKABLE_PADDING),
            ];

            foreach ($files as $name => $contents) {
                if (! $disk->put($directory.'/'.$name, $contents)) {
                    throw new RuntimeException('The favicon set could not be stored.');
                }
            }
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        return $directory;
    }

    /**
     * Decode the source image and fit it, centered, on a transparent square canvas.
     */
    private function squareSource(string $sourceImage): GdImage
    {
        $image = @imagecreatefromstring($sourceImage);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The favicon source image could not be decoded.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $size = max($width, $height);
        $square = $this->canvas($size);

        imagecopy($square, $image, intdiv($size - $width, 2), intdiv($size - $height, 2), 0, 0, $width, $height);

        return $square;
    }

    private function png(GdImage $source, int $size, ?string $backgroundColor = null, float $padding = 0.0): string
    {
        $canvas = $this->canvas($size, $backgroundColor);
        $inset = (int) round($size * $padding);
        $target = $size - ($inset * 2);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, $inset, $inset, 0, 0, $target, $target, imagesx($source), imagesy($source));
        imagealphablending($canvas, false);

        ob_start();
        imagepng($canvas, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * Build a multi-resolution ICO file using PNG-compressed entries.
     */
    private function ico(GdImage $source): string
    {
        $images = array_map(fn (int $size): string => $this->png($source, $size), self::ICO_SIZES);
        $header = pack('vvv', 0, 1, count($images));
        $directory = '';
        $offset = 6 + (16 * count($images));

        foreach (self::ICO_SIZES as $index => $size) {
            $length = strlen($images[$index]);
            $directory .= pack('CCCCvvVV', $size % 256, $size % 256, 0, 0, 1, 32, $length, $offset);
            $offset += $length;
        }

        return $header.$directory.implode('', $images);
    }

    private function canvas(int $size, ?string $backgroundColor = null): GdImage
    {
        $size = max(1, $size);
        $canvas = imagecreatetruecolor($size, $size);

        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('The favicon canvas could not be created.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $fill = $backgroundColor === null
            ? imagecolorallocatealpha($canvas, 0, 0, 0, 127)
            : imagecolorallocate(
                $canvas,
                $this->colorChannel($backgroundColor, 1),
                $this->colorChannel($backgroundColor, 3),
                $this->colorChannel($backgroundColor, 5),
            );

        imagefilledrectangle($canvas, 0, 0, $size - 1, $size - 1, (int) $fill);

        return $canvas;
    }

    /**
     * @return int<0, 255>
     */
    private function colorChannel(string $hexColor, int $offset): int
    {
        return min(255, max(0, (int) hexdec(substr($hexColor, $offset, 2))));
    }
}
