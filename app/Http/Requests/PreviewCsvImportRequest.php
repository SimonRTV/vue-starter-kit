<?php

namespace App\Http\Requests;

use App\Actions\Imports\ImportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(ImportRegistry::class)->get((string) $this->route('resource'))->authorize();

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $definition = app(ImportRegistry::class)->get((string) $this->route('resource'))->definition();
        $rules = [
            'mapping' => ['required', 'array:'.implode(',', array_keys($definition['fields']))],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:49'],
            'duplicate_mode' => ['required', Rule::in(['skip', 'update'])],
        ];
        foreach ($definition['required'] as $field) {
            $rules['mapping.'.$field] = ['required', 'integer', 'min:0', 'max:49'];
        }

        return $rules;
    }
}
