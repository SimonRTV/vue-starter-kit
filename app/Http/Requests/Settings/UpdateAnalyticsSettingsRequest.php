<?php

namespace App\Http\Requests\Settings;

use App\Actions\ApplicationSettings\AnalyticsSettings;
use App\Models\ApplicationSetting;
use Illuminate\Foundation\Http\FormRequest;

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

    /** @return array{enabled: bool, measurement_id: string, exclude_authenticated: bool} */
    public function settings(): array
    {
        return [
            'enabled' => $this->boolean('enabled'),
            'measurement_id' => (string) $this->validated('measurement_id', ''),
            'exclude_authenticated' => $this->boolean('exclude_authenticated'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $measurementId = $this->input('measurement_id');

        if (is_string($measurementId)) {
            $this->merge(['measurement_id' => strtoupper(trim($measurementId))]);
        }
    }
}
