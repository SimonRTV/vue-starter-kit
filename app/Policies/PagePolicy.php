<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public const VIEW = 'pages.view';

    public const CREATE = 'pages.create';

    public const UPDATE = 'pages.update';

    public const DELETE = 'pages.delete';

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
        self::VIEW => 'View pages and their content.',
        self::CREATE => 'Create new pages.',
        self::UPDATE => 'Edit existing pages.',
        self::DELETE => 'Delete, restore, and permanently delete pages.',
    ];

    /**
     * @var list<string>
     */
    public const SENSITIVE_PERMISSIONS = [
        self::DELETE,
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
    public function view(User $user, Page $page): bool
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
    public function update(User $user, Page $page): bool
    {
        return $user->can(self::UPDATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Page $page): bool
    {
        return $user->can(self::DELETE);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Page $page): bool
    {
        return $user->can(self::DELETE);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Page $page): bool
    {
        return $user->can(self::DELETE);
    }
}
