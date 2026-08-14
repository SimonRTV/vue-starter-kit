<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public const ASSIGN_ADMINISTRATOR = 'users.assign_administrator';

    public const ASSIGN_ROLES = 'users.assign_roles';

    public const CREATE = 'users.create';

    public const DELETE = 'users.delete';

    public const RESET_PASSWORD = 'users.reset_password';

    public const RESET_SECURITY = 'users.reset_security';

    public const SUSPEND = 'users.suspend';

    public const UPDATE = 'users.update';

    public const VERIFY_EMAIL = 'users.verify_email';

    public const VIEW = 'users.view';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        self::VIEW,
        self::CREATE,
        self::UPDATE,
        self::DELETE,
        self::SUSPEND,
        self::RESET_PASSWORD,
        self::RESET_SECURITY,
        self::VERIFY_EMAIL,
        self::ASSIGN_ROLES,
        self::ASSIGN_ADMINISTRATOR,
    ];

    /**
     * @var array<string, string>
     */
    public const PERMISSION_DESCRIPTIONS = [
        self::VIEW => 'View user accounts and their access details.',
        self::CREATE => 'Create new user accounts.',
        self::UPDATE => 'Change user names and email addresses.',
        self::DELETE => 'Permanently delete user accounts.',
        self::SUSPEND => 'Disable or re-enable user accounts and revoke their sessions.',
        self::RESET_PASSWORD => 'Send users a secure password reset link.',
        self::RESET_SECURITY => 'Reset two-factor authentication and passkeys, revoke sessions, and send a password reset link.',
        self::VERIFY_EMAIL => 'Mark user email addresses as verified or unverified.',
        self::ASSIGN_ROLES => 'Assign or remove roles while managing users.',
        self::ASSIGN_ADMINISTRATOR => 'Assign or remove the protected Administrator role.',
    ];

    /**
     * @var list<string>
     */
    public const SENSITIVE_PERMISSIONS = [
        self::UPDATE,
        self::DELETE,
        self::SUSPEND,
        self::RESET_PASSWORD,
        self::RESET_SECURITY,
        self::VERIFY_EMAIL,
        self::ASSIGN_ROLES,
        self::ASSIGN_ADMINISTRATOR,
    ];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(self::VIEW);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $managedUser): bool
    {
        return $user->can(self::VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(self::CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $managedUser): bool
    {
        return $user->can(self::UPDATE)
            && $this->canManageAdministrator($user, $managedUser);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $managedUser): bool
    {
        return ! $user->is($managedUser)
            && $user->can(self::DELETE)
            && $this->canManageAdministrator($user, $managedUser);
    }

    public function disable(User $user, User $managedUser): bool
    {
        return ! $user->is($managedUser)
            && $managedUser->disabled_at === null
            && $user->can(self::SUSPEND)
            && $this->canManageAdministrator($user, $managedUser);
    }

    public function enable(User $user, User $managedUser): bool
    {
        return ! $user->is($managedUser)
            && $managedUser->disabled_at !== null
            && $user->can(self::SUSPEND)
            && $this->canManageAdministrator($user, $managedUser);
    }

    public function resetPassword(User $user, User $managedUser): bool
    {
        return ! $user->is($managedUser)
            && $user->can(self::RESET_PASSWORD)
            && $this->canManageAdministrator($user, $managedUser);
    }

    public function resetSecurity(User $user, User $managedUser): bool
    {
        return ! $user->is($managedUser)
            && $user->can(self::RESET_SECURITY)
            && $this->canManageAdministrator($user, $managedUser);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $managedUser): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $managedUser): bool
    {
        return false;
    }

    private function canManageAdministrator(User $user, User $managedUser): bool
    {
        return ! $managedUser->hasRole(RolePolicy::ADMINISTRATOR_ROLE)
            || $user->hasRole(RolePolicy::ADMINISTRATOR_ROLE);
    }
}
