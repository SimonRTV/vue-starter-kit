<?php

namespace App\Http\Requests;

use App\Models\Media;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('media')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $media = $this->route('media');
        $canKeepPublic = $media instanceof Media && $media->visibility === 'public';

        return [
            'title' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', Rule::in($canKeepPublic || $this->user()?->can(MediaPolicy::PUBLISH) ? ['private', 'public'] : ['private'])],
        ];
    }
}
