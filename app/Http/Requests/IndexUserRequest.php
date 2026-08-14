<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class IndexUserRequest extends FormRequest
{
    private const DEFAULT_DIRECTION = 'desc';

    private const DEFAULT_PER_PAGE = 10;

    private const DEFAULT_SORT = 'created_at';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
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
            'role' => [
                'nullable',
                'string',
                Rule::exists(Role::class, 'name')->where('guard_name', 'web'),
            ],
            'verification' => ['nullable', Rule::in(['verified', 'unverified'])],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
            'sort' => ['nullable', Rule::in(['name', 'email', 'email_verified_at', 'disabled_at', 'last_login_at', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the normalized filters for the user listing.
     *
     * @return array{search: string|null, role: string|null, verification: 'verified'|'unverified'|null, status: 'active'|'disabled'|null, sort: 'name'|'email'|'email_verified_at'|'disabled_at'|'last_login_at'|'created_at'|'updated_at', direction: 'asc'|'desc', per_page: 10|25|50}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $search = $validated['search'] ?? null;
        $role = $validated['role'] ?? null;

        return [
            'search' => is_string($search) && $search !== '' ? $search : null,
            'role' => is_string($role) && $role !== '' ? $role : null,
            'verification' => match ($validated['verification'] ?? null) {
                'verified' => 'verified',
                'unverified' => 'unverified',
                default => null,
            },
            'status' => match ($validated['status'] ?? null) {
                'active' => 'active',
                'disabled' => 'disabled',
                default => null,
            },
            'sort' => match ($validated['sort'] ?? null) {
                'name' => 'name',
                'email' => 'email',
                'email_verified_at' => 'email_verified_at',
                'disabled_at' => 'disabled_at',
                'last_login_at' => 'last_login_at',
                'updated_at' => 'updated_at',
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

        if ($filters['role'] !== null) {
            $query['role'] = $filters['role'];
        }

        if ($filters['verification'] !== null) {
            $query['verification'] = $filters['verification'];
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
