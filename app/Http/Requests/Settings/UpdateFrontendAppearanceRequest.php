<?php

namespace App\Http\Requests\Settings;

use App\Actions\ApplicationSettings\FrontendAppearanceSettings;
use App\Models\ApplicationSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class UpdateFrontendAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', ApplicationSetting::class) ?? false;
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(FrontendAppearanceSettings::MODES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mode.required' => 'Choisissez un mode d’affichage.',
            'mode.in' => 'Choisissez un mode d’affichage valide.',
        ];
    }

    /** @return array{mode: 'toggle'|'system'|'light'|'dark'} */
    public function settings(): array
    {
        /** @var 'toggle'|'system'|'light'|'dark' $mode */
        $mode = $this->validated('mode');

        return ['mode' => $mode];
    }
}
