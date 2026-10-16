<?php

namespace App\Http\Requests;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Media::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.max(config('media.per_page_options'))],
            'ids.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['public', 'private', 'delete', 'move'])],
            'folder_id' => ['present_if:action,move', 'nullable', 'integer', 'exists:media_folders,id'],
        ];
    }
}
