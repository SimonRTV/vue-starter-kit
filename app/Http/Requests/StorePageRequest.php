<?php

namespace App\Http\Requests;

use App\Models\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Page::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(Page::class)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'is_published' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the validated page attributes.
     *
     * @return array{title: string, slug: string, excerpt: string|null, body: string|null, is_published: bool}
     */
    public function pageAttributes(): array
    {
        $validated = $this->validated();

        return [
            'title' => Arr::string($validated, 'title'),
            'slug' => Arr::string($validated, 'slug'),
            'excerpt' => Arr::get($validated, 'excerpt') === null
                ? null
                : Arr::string($validated, 'excerpt'),
            'body' => Arr::get($validated, 'body') === null
                ? null
                : Arr::string($validated, 'body'),
            'is_published' => in_array(
                Arr::get($validated, 'is_published'),
                [true, 1, '1'],
                true,
            ),
        ];
    }
}
