<?php

namespace App\Http\Requests;

use App\Models\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexPageRequest extends FormRequest
{
    private const DEFAULT_DIRECTION = 'desc';

    private const DEFAULT_PER_PAGE = 10;

    private const DEFAULT_SORT = 'updated_at';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Page::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'sort' => ['nullable', Rule::in(['title', 'is_published', 'published_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the normalized filters for the page listing.
     *
     * @return array{search: string|null, status: 'draft'|'published'|null, sort: 'title'|'is_published'|'published_at'|'updated_at', direction: 'asc'|'desc', per_page: 10|25|50}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $search = $validated['search'] ?? null;

        return [
            'search' => is_string($search) && $search !== '' ? $search : null,
            'status' => match ($validated['status'] ?? null) {
                'draft' => 'draft',
                'published' => 'published',
                default => null,
            },
            'sort' => match ($validated['sort'] ?? null) {
                'title' => 'title',
                'is_published' => 'is_published',
                'published_at' => 'published_at',
                default => self::DEFAULT_SORT,
            },
            'direction' => match ($validated['direction'] ?? null) {
                'asc' => 'asc',
                default => self::DEFAULT_DIRECTION,
            },
            'per_page' => match ($validated['per_page'] ?? null) {
                25, '25' => 25,
                50, '50' => 50,
                default => self::DEFAULT_PER_PAGE,
            },
        ];
    }

    /**
     * Get a canonical query string for the listing.
     *
     * @return array<string, int|string>
     */
    public function canonicalQuery(int $page): array
    {
        $filters = $this->filters();
        $query = [];

        if ($filters['search'] !== null) {
            $query['search'] = $filters['search'];
        }

        if ($filters['status'] !== null) {
            $query['status'] = $filters['status'];
        }

        if ($filters['sort'] !== self::DEFAULT_SORT) {
            $query['sort'] = $filters['sort'];
        }

        if ($filters['direction'] !== self::DEFAULT_DIRECTION) {
            $query['direction'] = $filters['direction'];
        }

        if ($filters['per_page'] !== self::DEFAULT_PER_PAGE) {
            $query['per_page'] = $filters['per_page'];
        }

        if ($page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => Str::squish($this->string('search')->toString()),
            ]);
        }
    }
}
