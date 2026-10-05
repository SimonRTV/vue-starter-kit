<?php

namespace App\Http\Requests;

use App\Actions\Pages\PageTemplates;
use App\Models\Page;
use App\Models\PageFieldSet;
use App\Models\PageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTemplates', Page::class) ?? false;
    }

    /** @return array{name: string, renderer: string, field_set_ids: list<int>} */
    public function templateAttributes(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'renderer' => $this->string('renderer')->toString(),
            'field_set_ids' => array_values(array_map(intval(...), $this->validated('field_set_ids'))),
        ];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'renderer' => ['required', 'string', Rule::in(array_keys(app(PageTemplates::class)->renderers())), function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! app(PageTemplates::class)->component(new PageTemplate(['renderer' => $value]))) {
                    $fail('Ce modèle de page est indisponible.');
                }
            }],
            'field_set_ids' => ['present', 'array', 'list', 'max:10'],
            'field_set_ids.*' => ['required', 'integer', 'distinct', Rule::exists(PageFieldSet::class, 'id')],
        ];
    }
}
