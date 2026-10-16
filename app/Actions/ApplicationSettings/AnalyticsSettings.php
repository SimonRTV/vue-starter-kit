<?php

namespace App\Actions\ApplicationSettings;

use App\Models\ApplicationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsSettings
{
    public const KEY = 'analytics.google';

    public const CACHE_KEY = 'application-settings.analytics.google';

    public const MEASUREMENT_ID_PATTERN = '/^GT?-[A-Z0-9]{4,20}$/';

    /** @return array{enabled: bool, measurement_id: string, exclude_authenticated: bool, require_consent: bool, privacy_policy_url: string} */
    public function get(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 300, static function (): array {
            $value = ApplicationSetting::query()->where('key', self::KEY)->value('value');
            $decoded = is_string($value) ? json_decode($value, true) : null;

            return is_array($decoded) ? $decoded : [];
        });

        $measurementId = $stored['measurement_id'] ?? null;
        $privacyPolicyUrl = $stored['privacy_policy_url'] ?? null;

        return [
            'enabled' => ($stored['enabled'] ?? false) === true,
            'measurement_id' => is_string($measurementId) && preg_match(self::MEASUREMENT_ID_PATTERN, $measurementId) === 1 ? $measurementId : '',
            'exclude_authenticated' => ($stored['exclude_authenticated'] ?? true) === true,
            'require_consent' => ($stored['require_consent'] ?? true) === true,
            'privacy_policy_url' => is_string($privacyPolicyUrl) && ApplicationSetting::isValidFrontendNavigationUrl($privacyPolicyUrl) ? $privacyPolicyUrl : '',
        ];
    }

    /**
     * Resolve the measurement ID to load for the request, limited to the public website.
     */
    public function measurementIdFor(Request $request): ?string
    {
        if (! config('starter.features.public_site') || ! $request->routeIs('home', 'content.show')) {
            return null;
        }

        $settings = $this->get();

        if (! $settings['enabled'] || $settings['measurement_id'] === '') {
            return null;
        }

        if ($settings['exclude_authenticated'] && $request->user() !== null) {
            return null;
        }

        return $settings['measurement_id'];
    }

    /**
     * Resolve the analytics configuration shared with the client for the request.
     *
     * @return array{measurementId: string|null, requiresConsent: bool, privacyPolicyUrl: string|null}
     */
    public function clientConfiguration(Request $request): array
    {
        $measurementId = $this->measurementIdFor($request);
        $settings = $this->get();

        return [
            'measurementId' => $measurementId,
            'requiresConsent' => $measurementId !== null && $settings['require_consent'],
            'privacyPolicyUrl' => $settings['privacy_policy_url'] !== '' ? $settings['privacy_policy_url'] : null,
        ];
    }
}
