<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteFavicons
{
    /**
     * Remove the custom favicon set while keeping the configured colors.
     */
    public function handle(): void
    {
        $directory = DB::transaction(function (): ?string {
            $applicationSetting = ApplicationSetting::query()
                ->where('key', FaviconSettings::KEY)
                ->lockForUpdate()
                ->first();
            $stored = is_string($applicationSetting?->value) ? json_decode($applicationSetting->value, true) : null;

            if ($applicationSetting === null || ! is_array($stored)) {
                return null;
            }

            $directory = $stored['directory'] ?? null;
            unset($stored['directory']);
            $applicationSetting->update(['value' => json_encode($stored, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT)]);

            return is_string($directory) ? $directory : null;
        });

        Cache::forget(FaviconSettings::CACHE_KEY);

        if ($directory !== null && FaviconSettings::isRasterDirectory($directory)) {
            Storage::disk('public')->deleteDirectory($directory);
        }
    }
}
