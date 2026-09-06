<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $categories = array_keys(config('notifications.categories', []));
        $rules = ['email' => ['required', Rule::array($categories)]];
        foreach ($categories as $category) {
            $rules['email.'.$category] = ['required', 'boolean'];
        }

        return $rules;
    }
}
