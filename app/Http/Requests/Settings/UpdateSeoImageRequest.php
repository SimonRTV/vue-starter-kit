<?php

namespace App\Http\Requests\Settings;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use LogicException;

class UpdateSeoImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', ApplicationSetting::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'mimes:jpg,jpeg,png,gif,webp',
                File::image()
                    ->max('5mb')
                    ->dimensions(
                        Rule::dimensions()
                            ->minWidth(200)
                            ->minHeight(200)
                            ->maxWidth(4096)
                            ->maxHeight(4096),
                    ),
            ],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.dimensions' => 'L’image doit mesurer entre 200 × 200 et 4096 × 4096 pixels. Le format 1200 × 630 pixels est recommandé.',
        ];
    }

    public function uploadedImage(): UploadedFile
    {
        $image = $this->file('image');

        if (! $image instanceof UploadedFile) {
            throw new LogicException('A validated social sharing image is required.');
        }

        return $image;
    }
}
