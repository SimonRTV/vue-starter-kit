<?php

namespace App\Http\Requests\Settings;

use App\Models\ApplicationSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', ApplicationSetting::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** @return array{name: string, tagline: string, contact_email: string, contact_phone: string} */
    public function settings(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'tagline' => (string) $this->validated('tagline', ''),
            'contact_email' => (string) $this->validated('contact_email', ''),
            'contact_phone' => (string) $this->validated('contact_phone', ''),
        ];
    }
}
