<?php

namespace App\Http\Requests\Settings;

use App\Actions\ApplicationSettings\FaviconSettings;
use App\Models\ApplicationSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFaviconColorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', ApplicationSetting::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'theme_color' => ['required', 'string', 'regex:'.FaviconSettings::COLOR_PATTERN],
            'background_color' => ['required', 'string', 'regex:'.FaviconSettings::COLOR_PATTERN],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'theme_color.regex' => 'Saisissez une couleur hexadécimale à six chiffres, par exemple #ff2d20.',
            'background_color.regex' => 'Saisissez une couleur hexadécimale à six chiffres, par exemple #ffffff.',
        ];
    }

    /** @return array{theme_color: string, background_color: string} */
    public function colors(): array
    {
        return [
            'theme_color' => (string) $this->validated('theme_color'),
            'background_color' => (string) $this->validated('background_color'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'theme_color' => is_string($this->input('theme_color')) ? strtolower(trim($this->input('theme_color'))) : null,
            'background_color' => is_string($this->input('background_color')) ? strtolower(trim($this->input('background_color'))) : null,
        ], fn (?string $value): bool => $value !== null));
    }
}
