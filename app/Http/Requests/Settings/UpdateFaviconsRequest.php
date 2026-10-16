<?php

namespace App\Http\Requests\Settings;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use LogicException;

class UpdateFaviconsRequest extends FormRequest
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
            'favicon' => [
                'required',
                'mimes:png,jpg,jpeg,webp',
                File::image()
                    ->max('4mb')
                    ->dimensions(
                        Rule::dimensions()
                            ->minWidth(512)
                            ->minHeight(512)
                            ->maxWidth(2048)
                            ->maxHeight(2048),
                    ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'favicon.dimensions' => 'L’image doit mesurer entre 512 × 512 et 2048 × 2048 pixels.',
        ];
    }

    public function favicon(): UploadedFile
    {
        $favicon = $this->file('favicon');

        if (! $favicon instanceof UploadedFile) {
            throw new LogicException('A validated favicon source image is required.');
        }

        return $favicon;
    }
}
