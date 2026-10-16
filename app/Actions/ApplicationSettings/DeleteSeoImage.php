<?php

namespace App\Actions\ApplicationSettings;

use App\Models\Page;
use Illuminate\Support\Facades\Storage;

class DeleteSeoImage
{
    public function __construct(private UpdateSeoSettings $updateSeoSettings) {}

    public function handle(string $target, string $field, ?Page $page = null): void
    {
        $imagePath = $this->updateSeoSettings->modify($target, $page, function (array $seo) use ($field): array {
            $imagePath = SeoSettings::imagePaths($seo)[$field] ?? null;
            unset($seo[$field.'_path']);

            return [$seo, $imagePath];
        });

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
