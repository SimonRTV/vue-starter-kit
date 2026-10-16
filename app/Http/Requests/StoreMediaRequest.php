<?php

namespace App\Http\Requests;

use App\Actions\Media\NormalizeMediaTags;
use App\Concerns\ValidatesMediaUploads;
use App\Models\Media;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use LogicException;

class StoreMediaRequest extends FormRequest
{
    use ValidatesMediaUploads;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'file' => $this->fileRules(),
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'visibility' => ['sometimes', Rule::in($this->user()?->can(MediaPolicy::PUBLISH) ? ['private', 'public'] : ['private'])],
            'optimize' => ['sometimes', 'boolean'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            ...$this->tagRules(),
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');
        if (! $file instanceof UploadedFile) {
            throw new LogicException('A validated file is required.');
        }

        return $file;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tags')) {
            $this->merge(['tags' => app(NormalizeMediaTags::class)->handle($this->input('tags'))]);
        }
    }
}
