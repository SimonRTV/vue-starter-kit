<?php

namespace App\Http\Requests\Settings;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSidebarFooterLinksRequest extends FormRequest
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
            'links' => ['present', 'array', 'list', 'max:10'],
            'links.*' => ['required', 'array:title,url'],
            'links.*.title' => ['required', 'string', 'max:80'],
            'links.*.url' => [
                'required',
                'string',
                'max:2048',
                'distinct',
                Rule::anyOf([
                    ['url:http,https'],
                    ['regex:'.ApplicationSetting::INTERNAL_SIDEBAR_FOOTER_LINK_PATTERN],
                ]),
            ],
        ];
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    public function links(): array
    {
        return array_values(array_map(
            static function (mixed $link): array {
                $validatedLink = is_array($link) ? $link : [];

                return [
                    'title' => Arr::string($validatedLink, 'title'),
                    'url' => Arr::string($validatedLink, 'url'),
                ];
            },
            Arr::array($this->validated(), 'links'),
        ));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'links.*.title.required' => 'Le libellé du lien #:position est obligatoire.',
            'links.*.url.required' => 'L’URL du lien #:position est obligatoire.',
            'links.*.url.any_of' => 'La destination du lien #:position doit être un chemin interne commençant par / ou une URL HTTP(S).',
            'links.*.url.distinct' => 'Chaque lien doit utiliser une URL différente.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $links = $this->input('links');

        if (! is_array($links)) {
            return;
        }

        $this->merge([
            'links' => array_map(static function (mixed $link): mixed {
                if (! is_array($link)) {
                    return $link;
                }

                if (isset($link['title']) && is_string($link['title'])) {
                    $link['title'] = Str::squish($link['title']);
                }

                if (isset($link['url']) && is_string($link['url'])) {
                    $link['url'] = Str::of($link['url'])->trim()->toString();
                }

                return $link;
            }, $links),
        ]);
    }
}
