<?php

namespace App\Concerns;

use Closure;
use Illuminate\Http\UploadedFile;

trait ValidatesMediaUploads
{
    /** @return list<mixed> */
    protected function fileRules(): array
    {
        $extensions = implode(',', config('media.extensions'));

        return ['bail', 'required', 'file', 'max:'.config('media.max_upload_kb'), 'mimes:'.$extensions, 'extensions:'.$extensions,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile || ! in_array($value->getMimeType(), config('media.image_mimes'), true)) {
                    return;
                }
                $dimensions = @getimagesize($value->getPathname());
                if ($dimensions === false || config('media.max_image_pixels') < $dimensions[0] * $dimensions[1]) {
                    $fail('L’image doit contenir au maximum 12 millions de pixels.');
                }
            },
        ];
    }

    /** @return array<string, list<string>> */
    protected function tagRules(): array
    {
        return [
            'tags' => ['sometimes', 'array', 'max:'.config('media.max_tags')],
            'tags.*' => ['string', 'max:'.config('media.max_tag_length')],
        ];
    }
}
