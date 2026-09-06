<?php

namespace App\Actions\Roles;

use App\Actions\Activity\RecordActivity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
            $role = Role::query()->lockForUpdate()->whereKey($role->getKey())->firstOrFail();
            $beforeName = $role->name;
            $beforePermissions = $role->permissions()->orderBy('name')->pluck('name')->all();
            $role->update(['name' => $name]);
            $role->syncPermissions($permissionNames);

            $changes = [];
            if ($beforeName !== $name) {
                $changes['name'] = ['before' => $beforeName, 'after' => $name];
            }
            $afterPermissions = $role->permissions()->orderBy('name')->pluck('name')->all();
            if ($beforePermissions !== $afterPermissions) {
                $changes['permissions'] = ['before' => $beforePermissions, 'after' => $afterPermissions];
            }
            if ($changes !== []) {
                $actor = Auth::user();
                app(RecordActivity::class)->handle('roles', (string) $role->getKey(), 'updated', $changes, $actor instanceof User ? $actor : null);
            }

            return $role->refresh();
        });
    }
}
