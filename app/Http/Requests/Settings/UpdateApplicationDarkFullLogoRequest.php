<?php

namespace App\Http\Requests\Settings;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use LogicException;

class UpdateApplicationDarkFullLogoRequest extends FormRequest
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
            'dark_full_logo' => [
                'required',
                'mimes:jpg,jpeg,png,gif,webp',
                File::image()
                    ->max('2mb')
                    ->dimensions(
                        Rule::dimensions()
                            ->maxWidth(4096)
                            ->maxHeight(4096),
                    ),
            ],
        ];
    }

    public function darkFullLogo(): UploadedFile
    {
        $darkFullLogo = $this->file('dark_full_logo');

        if (! $darkFullLogo instanceof UploadedFile) {
            throw new LogicException('A validated dark-background application logo is required.');
        }

        return $darkFullLogo;
    }
}
