<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateFavicons
{
    public function __construct(private FaviconSettings $faviconSettings, private GenerateFavicons $generateFavicons) {}

    public function handle(UploadedFile $source): void
    {
        $sourceImage = $source->get();

        if (! is_string($sourceImage)) {
            throw new RuntimeException('The favicon source image could not be read.');
        }

        $newDirectory = $this->generateFavicons->handle($sourceImage, $this->faviconSettings->get()['background_color']);

        try {
            $previousDirectory = DB::transaction(function () use ($newDirectory): ?string {
                $applicationSetting = ApplicationSetting::query()
                    ->where('key', FaviconSettings::KEY)
                    ->lockForUpdate()
                    ->first();
                $stored = is_string($applicationSetting?->value) ? json_decode($applicationSetting->value, true) : null;
                $stored = is_array($stored) ? $stored : [];
                $previousDirectory = $stored['directory'] ?? null;

                $applicationSetting ??= new ApplicationSetting(['key' => FaviconSettings::KEY]);
                $applicationSetting->value = json_encode([...$stored, 'directory' => $newDirectory], JSON_THROW_ON_ERROR);
                $applicationSetting->save();

                return is_string($previousDirectory) ? $previousDirectory : null;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->deleteDirectory($newDirectory);

            throw $exception;
        }

        Cache::forget(FaviconSettings::CACHE_KEY);

        if ($previousDirectory !== null && FaviconSettings::isRasterDirectory($previousDirectory)) {
            Storage::disk('public')->deleteDirectory($previousDirectory);
        }
    }
}
