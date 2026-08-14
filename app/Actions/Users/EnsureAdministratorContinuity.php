<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Policies\RolePolicy;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class EnsureAdministratorContinuity
{
    /**
     * @param  array{name: string, email: string, email_verified_at: CarbonInterface|null}  $attributes
     * @param  list<string>  $roleNames
     */
    public function updating(
        User $user,
        array $attributes,
        array $roleNames,
        string $verificationErrorKey = 'email_verified',
    ): void {
        $administrator = $this->lockedAdministratorRole();

        if ($administrator === null || ! $user->hasRole($administrator)) {
            return;
        }

        $willRemainAdministrator = in_array(
            RolePolicy::ADMINISTRATOR_ROLE,
            $roleNames,
            true,
        );
        $willRemainVerified = $attributes['email_verified_at'] !== null;

        if (
            ($willRemainAdministrator && $willRemainVerified)
            || $this->hasAnotherActiveVerifiedAdministrator($administrator, $user)
        ) {
            return;
        }

        $field = $willRemainAdministrator ? $verificationErrorKey : 'roles';

        throw ValidationException::withMessages([
            $field => __('At least one active, verified Administrator must remain.'),
        ]);
    }

    public function deleting(User $user): void
    {
        $administrator = $this->lockedAdministratorRole();

        if (
            $administrator === null
            || ! $user->hasRole($administrator)
            || $this->hasAnotherActiveVerifiedAdministrator($administrator, $user)
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => __('The final active, verified Administrator cannot be deleted.'),
        ]);
    }

    public function disabling(User $user): void
    {
        $administrator = $this->lockedAdministratorRole();

        if (
            $administrator === null
            || ! $user->hasRole($administrator)
            || $this->hasAnotherActiveVerifiedAdministrator($administrator, $user)
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => __('The final active, verified Administrator cannot be disabled.'),
        ]);
    }

    private function lockedAdministratorRole(): ?Role
    {
        return Role::query()
            ->where('name', RolePolicy::ADMINISTRATOR_ROLE)
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->first();
    }

    private function hasAnotherActiveVerifiedAdministrator(Role $administrator, User $user): bool
    {
        return $administrator->users()
            ->whereKeyNot($user->getKey())
            ->whereNotNull('email_verified_at')
            ->whereNull('disabled_at')
            ->exists();
    }
}
