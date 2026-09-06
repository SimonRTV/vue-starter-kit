<?php

namespace App\Http\Requests;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Page::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'action' => ['required', Rule::in(['publish', 'unpublish'])],
        ];
    }
}
