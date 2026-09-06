<?php

namespace App\Http\Requests;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;

class IndexMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Media::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'visibility' => ['nullable', 'in:private,public'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
