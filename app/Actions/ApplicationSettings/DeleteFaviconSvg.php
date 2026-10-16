<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteFaviconSvg
{
    public function handle(): void
    {
        $svgPath = DB::transaction(function (): ?string {
            $applicationSetting = ApplicationSetting::query()
                ->where('key', FaviconSettings::KEY)
                ->lockForUpdate()
                ->first();
            $stored = is_string($applicationSetting?->value) ? json_decode($applicationSetting->value, true) : null;

            if ($applicationSetting === null || ! is_array($stored)) {
                return null;
            }

            $svgPath = $stored['svg_path'] ?? null;
            unset($stored['svg_path']);
            $applicationSetting->update(['value' => json_encode($stored, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT)]);

            return is_string($svgPath) ? $svgPath : null;
        });

        Cache::forget(FaviconSettings::CACHE_KEY);

        if ($svgPath !== null && FaviconSettings::isSvgPath($svgPath)) {
            Storage::disk('public')->delete($svgPath);
        }
    }
}
