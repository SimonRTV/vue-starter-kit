<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;

class UpdateGeneralSettings
{
    /** @param array{name: string, tagline: string, contact_email: string, contact_phone: string} $attributes */
    public function handle(array $attributes): void
    {
        ApplicationSetting::query()->updateOrCreate(
            ['key' => GeneralSettings::KEY],
            ['value' => json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
        );
        Cache::forget(GeneralSettings::CACHE_KEY);
    }
}
