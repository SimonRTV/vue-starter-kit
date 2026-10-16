<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;

class UpdateAnalyticsSettings
{
    /** @param array{enabled: bool, measurement_id: string, exclude_authenticated: bool, require_consent: bool, privacy_policy_url: string} $attributes */
    public function handle(array $attributes): void
    {
        ApplicationSetting::query()->updateOrCreate(
            ['key' => AnalyticsSettings::KEY],
            ['value' => json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
        Cache::forget(AnalyticsSettings::CACHE_KEY);
    }
}
