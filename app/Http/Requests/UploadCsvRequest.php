<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:2048', 'extensions:csv,txt', 'mimetypes:text/plain,text/csv,text/x-csv,application/csv,application/vnd.ms-excel'],
            'delimiter' => ['required', Rule::in([',', ';', 'tab'])],
        ];
    }
}
