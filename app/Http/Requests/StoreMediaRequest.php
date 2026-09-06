<?php

namespace App\Http\Requests;

use App\Models\Media;
use App\Policies\MediaPolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use LogicException;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $extensions = implode(',', config('media.extensions'));

        return [
            'file' => ['bail', 'required', 'file', 'max:'.config('media.max_upload_kb'), 'mimes:'.$extensions, 'extensions:'.$extensions,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! in_array($value->getMimeType(), config('media.image_mimes'), true)) {
                        return;
                    }
                    $dimensions = @getimagesize($value->getPathname());
                    if ($dimensions === false || config('media.max_image_pixels') < $dimensions[0] * $dimensions[1]) {
                        $fail('L’image doit contenir au maximum 12 millions de pixels.');
                    }
                },
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:500'],
            'visibility' => ['sometimes', Rule::in($this->user()?->can(MediaPolicy::PUBLISH) ? ['private', 'public'] : ['private'])],
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
