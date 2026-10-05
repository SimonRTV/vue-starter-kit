<?php

namespace App\Http\Requests;

use App\Actions\Pages\TemplateFields;
use App\Models\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->can('update', $this->route('page')) ?? false)
            && ($this->input('intent') !== 'preview' || ($this->user()->can('view', $this->route('page')) && config('starter.features.public_site')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...app(TemplateFields::class)->rules($this, $this->route('page')),
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Page::class)->ignore($this->route('page')),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:200000'],
            'body_format' => ['sometimes', 'required', Rule::in(['text', 'html'])],
            'is_published' => ['required', 'boolean'],
            'intent' => ['sometimes', 'required', Rule::in(['save', 'preview', 'publish', 'unpublish'])],
        ];
    }

    /**
     * Get the validated page attributes.
     *
     * @return array{title: string, slug: string, excerpt: string|null, body: string|null, body_format: string, is_published: bool}
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
            'body_format' => Arr::get($validated, 'body_format', 'text'),
            'body' => Arr::get($validated, 'body') === null
                ? null
                : Arr::string($validated, 'body'),
            'is_published' => match ($this->validated('intent')) {
                'save', 'preview', 'unpublish' => false,
                'publish' => true,
                default => in_array(Arr::get($validated, 'is_published'), [true, 1, '1'], true),
            },
        ] + app(TemplateFields::class)->attributes($this, $this->route('page'));
    }
}
