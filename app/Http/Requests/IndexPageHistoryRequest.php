<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPageHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('page')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'history_page' => ['nullable', 'integer', 'min:1'],
            'revision' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
