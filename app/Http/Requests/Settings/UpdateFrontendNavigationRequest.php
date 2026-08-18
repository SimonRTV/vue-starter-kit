<?php

namespace App\Http\Requests\Settings;

use App\Models\ApplicationSetting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class UpdateFrontendNavigationRequest extends FormRequest
{
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
            'items' => ['present', 'array', 'list', 'max:10'],
            'items.*' => ['required', 'array:type,label,url,children'],
            'items.*.type' => ['required', 'string', Rule::in(['link', 'group'])],
            'items.*.label' => ['required', 'string', 'max:80'],
            'items.*.url' => [
                'nullable',
                'string',
                'max:2048',
                Rule::anyOf([
                    ['url:http,https'],
                    ['regex:'.ApplicationSetting::FRONTEND_NAVIGATION_DESTINATION_PATTERN],
                ]),
            ],
            'items.*.children' => ['present', 'array', 'list', 'max:8'],
            'items.*.children.*' => ['required', 'array:label,url,description'],
            'items.*.children.*.label' => ['required', 'string', 'max:80'],
            'items.*.children.*.url' => [
                'required',
                'string',
                'max:2048',
                Rule::anyOf([
                    ['url:http,https'],
                    ['regex:'.ApplicationSetting::FRONTEND_NAVIGATION_DESTINATION_PATTERN],
                ]),
            ],
            'items.*.children.*.description' => ['present', 'nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return list<Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (Arr::array($this->input(), 'items') as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $type = $item['type'] ?? null;
                    $url = $item['url'] ?? null;
                    $children = $item['children'] ?? null;

                    if ($type === 'link') {
                        if (! is_string($url) || $url === '') {
                            $validator->errors()->add(
                                "items.{$index}.url",
                                'La destination du lien est obligatoire.',
                            );
                        }

                        if (is_array($children) && $children !== []) {
                            $validator->errors()->add(
                                "items.{$index}.children",
                                'Un lien direct ne peut pas contenir de sous-liens.',
                            );
                        }
                    }

                    if ($type === 'group') {
                        if ($url !== null && $url !== '') {
                            $validator->errors()->add(
                                "items.{$index}.url",
                                'Un menu déroulant ne peut pas avoir sa propre destination.',
                            );
                        }

                        if (! is_array($children) || $children === []) {
                            $validator->errors()->add(
                                "items.{$index}.children",
                                'Un menu déroulant doit contenir au moins un lien.',
                            );
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{
     *     type: 'link'|'group',
     *     label: string,
     *     url: string|null,
     *     children: list<array{label: string, url: string, description: string}>
     * }>
     */
    public function items(): array
    {
        return array_values(array_map(
            static function (mixed $item): array {
                $validatedItem = is_array($item) ? $item : [];
                $type = match (Arr::string($validatedItem, 'type')) {
                    'link' => 'link',
                    'group' => 'group',
                    default => throw new LogicException('The validated navigation item type is invalid.'),
                };
                $children = array_values(array_map(
                    static function (mixed $child): array {
                        $validatedChild = is_array($child) ? $child : [];

                        return [
                            'label' => Arr::string($validatedChild, 'label'),
                            'url' => Arr::string($validatedChild, 'url'),
                            'description' => is_string($validatedChild['description'] ?? null)
                                ? $validatedChild['description']
                                : '',
                        ];
                    },
                    Arr::array($validatedItem, 'children'),
                ));

                return [
                    'type' => $type,
                    'label' => Arr::string($validatedItem, 'label'),
                    'url' => $type === 'link' && is_string($validatedItem['url'] ?? null)
                        ? $validatedItem['url']
                        : null,
                    'children' => $type === 'group' ? $children : [],
                ];
            },
            Arr::array($this->validated(), 'items'),
        ));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.label.required' => 'Le libellé de l’élément #:position est obligatoire.',
            'items.*.url.any_of' => 'La destination doit être une ancre, un chemin interne ou une URL HTTP(S).',
            'items.*.children.*.label.required' => 'Le libellé du sous-lien est obligatoire.',
            'items.*.children.*.url.required' => 'La destination du sous-lien est obligatoire.',
            'items.*.children.*.url.any_of' => 'La destination du sous-lien doit être une ancre, un chemin interne ou une URL HTTP(S).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $this->merge([
            'items' => array_map(static function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                if (isset($item['label']) && is_string($item['label'])) {
                    $item['label'] = Str::squish($item['label']);
                }

                if (isset($item['url']) && is_string($item['url'])) {
                    $trimmedUrl = Str::of($item['url'])->trim()->toString();
                    $item['url'] = $trimmedUrl === '' ? null : $trimmedUrl;
                }

                if (isset($item['children']) && is_array($item['children'])) {
                    $item['children'] = array_map(static function (mixed $child): mixed {
                        if (! is_array($child)) {
                            return $child;
                        }

                        foreach (['label', 'description'] as $field) {
                            if (isset($child[$field]) && is_string($child[$field])) {
                                $child[$field] = Str::squish($child[$field]);
                            }
                        }

                        if (isset($child['url']) && is_string($child['url'])) {
                            $child['url'] = Str::of($child['url'])->trim()->toString();
                        }

                        return $child;
                    }, $item['children']);
                }

                return $item;
            }, $items),
        ]);
    }
}
