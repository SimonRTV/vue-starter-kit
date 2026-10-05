<?php

namespace App\Http\Requests;

use App\Models\Page;
use App\Models\PageFieldSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePageFieldSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTemplates', Page::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $set = $this->route('page_field_set');

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::notIn(['__proto__', 'constructor', 'prototype']), Rule::unique(PageFieldSet::class, 'key')->ignore($set),
                Rule::when($set instanceof PageFieldSet && $set->templates()->exists(), Rule::in([$set instanceof PageFieldSet ? $set->key : null]))],
            'fields' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'fields.*' => ['required', 'array:key,label,type,required,options'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct', Rule::notIn(['__proto__', 'constructor', 'prototype'])],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'number', 'boolean', 'select', 'image', 'collection'])],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.options' => ['sometimes', 'array', 'list', 'max:50'],
            'fields.*.options.*' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->validated('fields') as $index => $field) {
                if ($field['type'] === 'select' && (empty($field['options']) || count(array_unique($field['options'])) !== count($field['options']))) {
                    $validator->errors()->add('fields.'.$index.'.options', 'Ajoutez au moins un choix.');
                }
            }
        }];
    }
}
