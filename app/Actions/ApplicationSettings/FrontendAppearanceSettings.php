<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;

class FrontendAppearanceSettings
{
    public const KEY = 'appearance.frontend';

    public const CACHE_KEY = 'application-settings.appearance.frontend';

    /**
     * Visitors choose with the light/dark switch on the public website.
     */
    public const MODE_TOGGLE = 'toggle';

    /** @var list<'toggle'|'system'|'light'|'dark'> */
    public const MODES = [self::MODE_TOGGLE, 'system', 'light', 'dark'];

    /** @return array{mode: 'toggle'|'system'|'light'|'dark'} */
    public function get(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 300, static function (): array {
            $value = ApplicationSetting::query()->where('key', self::KEY)->value('value');
            $decoded = is_string($value) ? json_decode($value, true) : null;

            return is_array($decoded) ? $decoded : [];
        });

        $mode = $stored['mode'] ?? null;

        return [
            'mode' => in_array($mode, self::MODES, true) ? $mode : self::MODE_TOGGLE,
        ];
    }

    /**
     * Resolve the appearance forced on the public website, or null when visitors may choose.
     *
     * @return 'system'|'light'|'dark'|null
     */
    public function forcedAppearance(): ?string
    {
        $mode = $this->get()['mode'];

        return $mode === self::MODE_TOGGLE ? null : $mode;
    }
}
