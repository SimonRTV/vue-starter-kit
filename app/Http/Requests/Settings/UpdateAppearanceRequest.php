<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'appearance' => [
                'sometimes',
                'required',
                Rule::in(['light', 'dark', 'system']),
            ],
            'admin_theme' => [
                'sometimes',
                'required',
                Rule::in(['neutral', 'ocean', 'forest']),
            ],
        ];
    }
}
