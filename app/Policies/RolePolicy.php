<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public const ADMINISTRATOR_ROLE = 'Administrator';

    public const CREATE = 'roles.create';

    public const DELETE = 'roles.delete';

    public const UPDATE = 'roles.update';

    public const VIEW = 'roles.view';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        self::VIEW,
        self::CREATE,
        self::UPDATE,
        self::DELETE,
    ];

    /**
     * @var array<string, string>
     */
    public const PERMISSION_DESCRIPTIONS = [
        self::VIEW => 'View roles and their assigned permissions.',
        self::CREATE => 'Create roles and choose their permissions.',
        self::UPDATE => 'Change role names and assigned permissions.',
        self::DELETE => 'Delete roles that are not assigned to users.',
    ];

    /**
     * @var list<string>
     */
    public const SENSITIVE_PERMISSIONS = [
        self::CREATE,
        self::UPDATE,
        self::DELETE,
    ];

    public function viewAny(User $user): bool
    {
        return $user->can(self::VIEW);
    }

    public function view(User $user, Role $role): bool
    {
        return $role->guard_name === 'web' && $user->can(self::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(self::CREATE);
    }

    public function assign(User $user, Role $role): bool
    {
        if (
            $role->guard_name !== 'web'
            || ! $user->can(UserPolicy::ASSIGN_ROLES)
        ) {
            return false;
        }

        if (self::isProtected($role)) {
            return $user->hasRole(self::ADMINISTRATOR_ROLE)
                && $user->can(UserPolicy::ASSIGN_ADMINISTRATOR);
        }

        $role->loadMissing('permissions:id,name,guard_name');

        return $role->permissions->every(
            static fn (Permission $permission): bool => $user->can($permission->name),
        );
    }

    public function update(User $user, Role $role): bool
    {
        return $role->guard_name === 'web'
            && $user->can(self::UPDATE)
            && ! self::isProtected($role)
            && ! $user->hasRole($role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $role->guard_name === 'web'
            && $user->can(self::DELETE)
            && ! self::isProtected($role)
            && ! self::hasAssignedUsers($role);
    }

    public static function isProtected(Role $role): bool
    {
        return $role->guard_name === 'web'
            && $role->name === self::ADMINISTRATOR_ROLE;
    }

    private static function hasAssignedUsers(Role $role): bool
    {
        $usersCount = $role->getAttribute('users_count');

        if (is_numeric($usersCount)) {
            return (int) $usersCount > 0;
        }

        return $role->users()->exists();
    }
}
