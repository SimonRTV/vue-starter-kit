<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Policies\UserPolicy;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'email_verified' => ['required', 'boolean'],
            'roles' => ['present', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::exists(Role::class, 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * Get the additional validation callbacks for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();

                if (! $actor instanceof User || $validator->errors()->has('roles')) {
                    return;
                }

                if ($this->boolean('email_verified') && $actor->cannot(UserPolicy::VERIFY_EMAIL)) {
                    $validator->errors()->add(
                        'email_verified',
                        __('You cannot mark email addresses as verified.'),
                    );
                }

                $roles = Role::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', $this->requestedRoleNames())
                    ->with('permissions:id,name,guard_name')
                    ->get();

                if ($roles->contains(fn (Role $role): bool => $actor->cannot('assign', $role))) {
                    $validator->errors()->add(
                        'roles',
                        __('You cannot assign one or more of the selected roles.'),
                    );
                }
            },
        ];
    }

    /**
     * Get the validated user attributes.
     *
     * @return array{name: string, email: string, email_verified_at: CarbonInterface|null}
     */
    public function userAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => Arr::string($validated, 'name'),
            'email' => Arr::string($validated, 'email'),
            'email_verified_at' => $this->boolean('email_verified') ? now() : null,
        ];
    }

    /**
     * Get the validated role names.
     *
     * @return list<string>
     */
    public function roleNames(): array
    {
        return $this->requestedRoleNames($this->validated());
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @return list<string>
     */
    private function requestedRoleNames(?array $input = null): array
    {
        return array_values(array_map(
            static fn (mixed $role): string => (string) $role,
            Arr::array($input ?? $this->all(), 'roles'),
        ));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => User::normalizeEmail($this->string('email')->toString()),
            ]);
        }
    }
}
