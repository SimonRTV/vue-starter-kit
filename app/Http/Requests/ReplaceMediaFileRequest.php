<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesMediaUploads;
use App\Models\Media;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

class ReplaceMediaFileRequest extends FormRequest
{
    use ValidatesMediaUploads;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('media')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'file' => [...$this->fileRules(), function (string $attribute, mixed $value, Closure $fail): void {
                $media = $this->route('media');
                if ($media instanceof Media && $media->isImage() && $value instanceof UploadedFile
                    && ! in_array($value->getMimeType(), config('media.image_mimes'), true)) {
                    $fail('Une image ne peut être remplacée que par une autre image.');
                }
            }],
            'optimize' => ['sometimes', 'boolean'],
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
}
