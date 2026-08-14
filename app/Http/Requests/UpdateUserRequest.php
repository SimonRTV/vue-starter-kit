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

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $managedUser = $this->route('user');

        return [
            ...$this->profileRules($managedUser instanceof User ? $managedUser->id : null),
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
                $managedUser = $this->route('user');
                $actor = $this->user();

                if (
                    ! $managedUser instanceof User
                    || ! $actor instanceof User
                    || $validator->errors()->has('roles')
                ) {
                    return;
                }

                $requestedRoles = $this->requestedRoleNames();
                $currentRoles = $managedUser->roles()
                    ->where('guard_name', 'web')
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                if (! $actor->is($managedUser)) {
                    $changedRoleNames = collect([
                        ...array_diff($requestedRoles, $currentRoles),
                        ...array_diff($currentRoles, $requestedRoles),
                    ])->unique()->values()->all();
                    $changedRoles = Role::query()
                        ->where('guard_name', 'web')
                        ->whereIn('name', $changedRoleNames)
                        ->with('permissions:id,name,guard_name')
                        ->get();

                    if ($changedRoles->contains(
                        fn (Role $role): bool => $actor->cannot('assign', $role),
                    )) {
                        $validator->errors()->add(
                            'roles',
                            __('You cannot assign or remove one or more of the selected roles.'),
                        );
                    }

                    return;
                }

                sort($requestedRoles);

                if ($requestedRoles !== $currentRoles) {
                    $validator->errors()->add(
                        'roles',
                        __('You cannot change your own roles.'),
                    );
                }
            },
            function (Validator $validator): void {
                $managedUser = $this->route('user');
                $actor = $this->user();

                if (
                    ! $managedUser instanceof User
                    || ! $actor instanceof User
                    || $validator->errors()->has('email_verified')
                    || $actor->can(UserPolicy::VERIFY_EMAIL)
                ) {
                    return;
                }

                $emailChanged = User::normalizeEmail($this->string('email')->toString())
                    !== $managedUser->email;
                $verificationChanged = $this->boolean('email_verified')
                    !== ($managedUser->email_verified_at !== null);

                if ($verificationChanged && ! $emailChanged) {
                    $validator->errors()->add(
                        'email_verified',
                        __('You cannot change email verification status.'),
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
        $managedUser = $this->route('user');
        $actor = $this->user();
        $email = Arr::string($validated, 'email');
        $emailChanged = $managedUser instanceof User && $email !== $managedUser->email;
        $mayVerifyEmail = $actor instanceof User && $actor->can(UserPolicy::VERIFY_EMAIL);

        return [
            'name' => Arr::string($validated, 'name'),
            'email' => $email,
            'email_verified_at' => $this->boolean('email_verified') && (! $emailChanged || $mayVerifyEmail)
                ? ($managedUser instanceof User ? $managedUser->email_verified_at : null) ?? now()
                : null,
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
