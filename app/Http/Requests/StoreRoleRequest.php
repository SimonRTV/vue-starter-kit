<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Role::class, 'name')->where('guard_name', 'web'),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                Rule::exists(Permission::class, 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function roleName(): string
    {
        return Arr::string($this->validated(), 'name');
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_values(array_map(
            static fn (mixed $permission): string => (string) $permission,
            Arr::array($this->validated(), 'permissions'),
        ));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => Str::squish($this->string('name')->toString()),
            ]);
        }
    }
}
