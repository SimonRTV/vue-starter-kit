<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateFaviconColors
{
    public function __construct(private FaviconSettings $faviconSettings, private GenerateFavicons $generateFavicons) {}

    /**
     * Store the theme and background colors, regenerating custom icons that embed the background.
     *
     * @param  array{theme_color: string, background_color: string}  $colors
     */
    public function handle(array $colors): void
    {
        $current = $this->faviconSettings->get();
        $disk = Storage::disk('public');
        $newDirectory = null;

        if ($current['directory'] !== null && $current['background_color'] !== $colors['background_color']) {
            $sourceImage = $disk->get($current['directory'].'/'.FaviconSettings::SOURCE_FILE);

            if (is_string($sourceImage)) {
                $newDirectory = $this->generateFavicons->handle($sourceImage, $colors['background_color']);
            }
        }

        try {
            $previousDirectory = DB::transaction(function () use ($colors, $newDirectory): ?string {
                $applicationSetting = ApplicationSetting::query()
                    ->where('key', FaviconSettings::KEY)
                    ->lockForUpdate()
                    ->first();
                $stored = is_string($applicationSetting?->value) ? json_decode($applicationSetting->value, true) : null;
                $stored = is_array($stored) ? $stored : [];
                $previousDirectory = $stored['directory'] ?? null;

                $applicationSetting ??= new ApplicationSetting(['key' => FaviconSettings::KEY]);
                $applicationSetting->value = json_encode([
                    ...$stored,
                    ...$colors,
                    ...($newDirectory !== null ? ['directory' => $newDirectory] : []),
                ], JSON_THROW_ON_ERROR);
                $applicationSetting->save();

                return $newDirectory !== null && is_string($previousDirectory) ? $previousDirectory : null;
            });
        } catch (Throwable $exception) {
            if ($newDirectory !== null) {
                $disk->deleteDirectory($newDirectory);
            }

            throw $exception;
        }

        Cache::forget(FaviconSettings::CACHE_KEY);

        if ($previousDirectory !== null && FaviconSettings::isRasterDirectory($previousDirectory)) {
            $disk->deleteDirectory($previousDirectory);
        }
    }
}
