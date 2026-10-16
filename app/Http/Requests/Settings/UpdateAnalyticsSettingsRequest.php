<?php

namespace App\Http\Requests\Settings;

use App\Actions\ApplicationSettings\AnalyticsSettings;
use App\Models\ApplicationSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAnalyticsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', ApplicationSetting::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'measurement_id' => ['required_if_accepted:enabled', 'nullable', 'string', 'regex:'.AnalyticsSettings::MEASUREMENT_ID_PATTERN],
            'exclude_authenticated' => ['nullable', 'boolean'],
            'require_consent' => ['nullable', 'boolean'],
            'privacy_policy_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'measurement_id.required_if_accepted' => 'Saisissez un identifiant de mesure pour activer Google Analytics.',
            'measurement_id.regex' => 'Saisissez un identifiant GA4 ou de balise Google valide, par exemple G-ABC123XYZ9 ou GT-ABC1234.',
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $url = $this->input('privacy_policy_url');
            if (is_string($url) && $url !== '' && ! $validator->errors()->has('privacy_policy_url')
                && ! ApplicationSetting::isValidFrontendNavigationUrl($url)) {
                $validator->errors()->add('privacy_policy_url', 'Saisissez un chemin interne (par exemple /content/confidentialite) ou une URL HTTP(S).');
            }
        }];
    }

    /** @return array{enabled: bool, measurement_id: string, exclude_authenticated: bool, require_consent: bool, privacy_policy_url: string} */
    public function settings(): array
    {
        return [
            'enabled' => $this->boolean('enabled'),
            'measurement_id' => (string) $this->validated('measurement_id', ''),
            'exclude_authenticated' => $this->boolean('exclude_authenticated'),
            'require_consent' => $this->boolean('require_consent'),
            'privacy_policy_url' => (string) $this->validated('privacy_policy_url', ''),
        ];
    }

    protected function prepareForValidation(): void
    {
        $measurementId = $this->input('measurement_id');
        $privacyPolicyUrl = $this->input('privacy_policy_url');

        $this->merge(array_filter([
            'measurement_id' => is_string($measurementId) ? strtoupper(trim($measurementId)) : null,
            'privacy_policy_url' => is_string($privacyPolicyUrl) ? trim($privacyPolicyUrl) : null,
        ], fn (?string $value): bool => $value !== null));
    }
}
