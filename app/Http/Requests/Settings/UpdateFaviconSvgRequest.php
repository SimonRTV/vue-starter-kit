<?php

namespace App\Http\Requests\Settings;

use App\Actions\ApplicationSettings\SanitizeFaviconSvg;
use App\Models\ApplicationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use LogicException;

class UpdateFaviconSvgRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', ApplicationSetting::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'svg' => ['required', 'file', 'extensions:svg', 'mimetypes:image/svg+xml,image/svg,text/xml,application/xml,text/plain', 'max:512'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'svg.extensions' => 'Le fichier doit être une image SVG.',
            'svg.mimetypes' => 'Le fichier doit être une image SVG.',
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('svg')) {
                return;
            }

            try {
                app(SanitizeFaviconSvg::class)->handle($this->svg());
            } catch (InvalidArgumentException) {
                $validator->errors()->add('svg', 'Le fichier SVG est illisible ou mal formé.');
            }
        }];
    }

    public function svg(): string
    {
        $svg = $this->file('svg');
        $contents = $svg instanceof UploadedFile ? $svg->get() : null;

        if (! is_string($contents)) {
            throw new LogicException('A validated SVG favicon is required.');
        }

        return $contents;
    }
}
