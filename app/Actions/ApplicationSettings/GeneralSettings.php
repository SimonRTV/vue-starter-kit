<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;

class GeneralSettings
{
    public const KEY = 'general.identity';

    public const CACHE_KEY = 'application-settings.general.identity';

    /** @return array{name: string, tagline: string, contact_email: string, contact_phone: string} */
    public function get(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 300, static function (): array {
            $value = ApplicationSetting::query()->where('key', self::KEY)->value('value');
            $decoded = is_string($value) ? json_decode($value, true) : null;

            return is_array($decoded) ? $decoded : [];
        });

        $defaults = ['name' => (string) config('app.name'), 'tagline' => '', 'contact_email' => '', 'contact_phone' => ''];
        foreach ($defaults as $key => $default) {
            $defaults[$key] = isset($stored[$key]) && is_string($stored[$key]) ? $stored[$key] : $default;
        }

        return $defaults;
    }
}
