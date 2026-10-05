<?php

namespace App\Actions\Pages;

use App\Models\Page;
use App\Models\PageFieldSet;
use App\Models\PageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** @phpstan-import-type FieldDefinition from PageFieldSet */
class TemplateFields
{
    public function __construct(private PageTemplates $templates) {}

    /** @return array<string, array<mixed>> */
    public function rules(FormRequest $request, ?Page $page = null): array
    {
        if ($page && ! $request->exists('page_template_id') && ! $request->exists('template_fields')) {
            return [];
        }

        $id = $request->input('page_template_id', $page?->page_template_id);
        $template = is_scalar($id) ? PageTemplate::query()->with('fieldSets')->find($id) : null;
        $rules = [
            'page_template_id' => ['nullable', 'integer', Rule::exists(PageTemplate::class, 'id'), function (string $attribute, mixed $value, \Closure $fail) use ($template): void {
                if (! $this->templates->component($template)) {
                    $fail('Ce modèle de page est indisponible.');
                }
            }],
            'template_fields' => ['nullable', 'array'],
        ];

        $sets = $template instanceof PageTemplate ? $template->fieldSets : collect();
        $rules['template_fields'][] = $sets->isEmpty() ? 'max:0' : Rule::array($sets->pluck('key')->all());
        foreach ($sets as $set) {
            $rules['template_fields.'.$set->key] = ['sometimes', 'array', Rule::array(array_column($set->fields, 'key'))];
            foreach ($set->fields as $field) {
                $path = 'template_fields.'.$set->key.'.'.$field['key'];
                $rules += $this->fieldRules($field, $path, $request->input($path));
            }
        }

        return $rules;
    }

    /** @return array{page_template_id?: int|null, template_fields?: array<string, array<string, mixed>>|null} */
    public function attributes(FormRequest $request, ?Page $page = null): array
    {
        if ($page && ! $request->exists('page_template_id') && ! $request->exists('template_fields')) {
            return [];
        }

        $validated = $request->validated();
        $id = Arr::get($validated, 'page_template_id', $page?->page_template_id);
        $template = is_numeric($id) ? PageTemplate::query()->with('fieldSets')->find((int) $id) : null;

        return [
            'page_template_id' => $template?->id,
            'template_fields' => $template ? $this->values($template, $validated['template_fields'] ?? []) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array<string, mixed>>
     */
    public function values(PageTemplate $template, array $values): array
    {
        $clean = [];
        foreach ($template->fieldSets as $set) {
            $clean[$set->key] = [];
            foreach ($set->fields as $field) {
                $value = Arr::get($values, $set->key.'.'.$field['key']);
                $validator = Validator::make(['value' => $value], $this->fieldRules($field, 'value', $value));
                if ($validator->fails()) {
                    $value = null;
                } elseif ($value !== null) {
                    $value = match ($field['type']) {
                        'boolean' => in_array($value, [true, 1, '1'], true),
                        'number' => (float) $value,
                        'collection' => [...$value, 'limit' => (int) $value['limit']],
                        default => $value,
                    };
                }
                $clean[$set->key][$field['key']] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  FieldDefinition  $field
     * @return array<string, array<mixed>>
     */
    private function fieldRules(array $field, string $path, mixed $value): array
    {
        $rules = [$path => [$field['required'] ? 'required' : 'nullable']];
        $rules[$path] = [...$rules[$path], ...match ($field['type']) {
            'text' => ['string', 'max:1000'],
            'textarea' => ['string', 'max:20000'],
            'image' => ['string', 'url:http,https', 'max:2048'],
            'number' => ['numeric', 'between:-1000000000,1000000000'],
            'boolean' => ['boolean'],
            'select' => ['string', Rule::in($field['options'] ?? [])],
            'collection' => ['array:source,limit,order_by,direction'],
            default => ['prohibited'],
        }];

        if ($field['type'] === 'collection' && $value !== null) {
            $sourceKey = is_array($value) && is_string($value['source'] ?? null) ? $value['source'] : '';
            $source = $this->templates->source($sourceKey);
            $rules[$path.'.source'] = ['required', 'string', Rule::in(array_column($this->templates->sources(), 'key'))];
            $rules[$path.'.limit'] = ['required', 'integer', 'between:1,100'];
            $rules[$path.'.order_by'] = ['required', 'string', Rule::in(array_keys($source?->orders() ?? []))];
            $rules[$path.'.direction'] = ['required', Rule::in(['asc', 'desc'])];
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function publicProps(Page $page): array
    {
        $template = $page->template;
        if (! $template || ! $this->templates->component($template)) {
            return [];
        }

        $values = $this->values($template, $page->template_fields ?? []);
        $collections = [];
        foreach ($template->fieldSets as $set) {
            foreach ($set->fields as $field) {
                $value = $values[$set->key][$field['key']];
                if ($field['type'] === 'collection' && is_array($value)) {
                    $collections[$set->key][$field['key']] = $this->templates->source($value['source'])
                        ?->items($page, $value['limit'], $value['order_by'], $value['direction']) ?? [];
                }
            }
        }

        return ['template' => $template, 'fields' => $values, 'collections' => $collections];
    }
}
