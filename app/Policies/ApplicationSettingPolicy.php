<?php

namespace App\Policies;

use App\Models\ApplicationSetting;
use App\Models\User;

class ApplicationSettingPolicy
{
    /** @var list<string> */
    public const PERMISSIONS = [];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ApplicationSetting $applicationSetting): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ApplicationSetting $applicationSetting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ApplicationSetting $applicationSetting): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ApplicationSetting $applicationSetting): bool
    {
        return false;
    }

    private function isAdministrator(User $user): bool
    {
        return $user->hasRole(RolePolicy::ADMINISTRATOR_ROLE);
    }
}
