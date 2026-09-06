<?php

namespace App\Http\Requests;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('page')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['media_id' => ['required', 'uuid', Rule::exists(Media::class, 'id')]];
    }
}
