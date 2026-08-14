<?php

namespace App\Actions\Roles;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UpdateRole
{
    /**
     * Update a role and synchronize its permissions.
     *
     * @param  list<string>  $permissionNames
     */
    public function handle(Role $role, string $name, array $permissionNames): Role
    {
        return DB::transaction(function () use ($role, $name, $permissionNames): Role {
            $role->update(['name' => $name]);
            $role->syncPermissions($permissionNames);

            return $role->refresh();
        });
    }
}
