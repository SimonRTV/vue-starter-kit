<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;

class UpdateFrontendAppearanceSettings
{
    /** @param array{mode: 'toggle'|'system'|'light'|'dark'} $attributes */
    public function handle(array $attributes): void
    {
        ApplicationSetting::query()->updateOrCreate(
            ['key' => FrontendAppearanceSettings::KEY],
            ['value' => json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
        Cache::forget(FrontendAppearanceSettings::CACHE_KEY);
    }
}
