<?php

namespace App\Actions\Roles;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateRole
{
    /**
     * Create a role and assign its permissions.
     *
     * @param  list<string>  $permissionNames
     */
    public function handle(string $name, array $permissionNames): Role
    {
        return DB::transaction(function () use ($name, $permissionNames): Role {
            $role = Role::query()->create([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($permissionNames);

            return $role;
        });
    }
}
