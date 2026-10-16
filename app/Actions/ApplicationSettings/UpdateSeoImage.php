<?php

namespace App\Actions\ApplicationSettings;

use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateSeoImage
{
    public function __construct(private UpdateSeoSettings $updateSeoSettings) {}

    /**
     * Store a social sharing image for a target, replacing its previous upload or pasted URL.
     */
    public function handle(string $target, string $field, UploadedFile $image, ?Page $page = null): void
    {
        $newImagePath = $image->store('seo/images', 'public');

        if (! is_string($newImagePath)) {
            throw new RuntimeException('The social sharing image could not be stored.');
        }

        try {
            $previousImagePath = $this->updateSeoSettings->modify($target, $page, function (array $seo) use ($field, $newImagePath): array {
                $previousImagePath = SeoSettings::imagePaths($seo)[$field] ?? null;
                $seo[$field] = '';
                $seo[$field.'_path'] = $newImagePath;

                return [$seo, $previousImagePath];
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newImagePath);

            throw $exception;
        }

        if ($previousImagePath !== null && $previousImagePath !== $newImagePath) {
            Storage::disk('public')->delete($previousImagePath);
        }
    }
}
