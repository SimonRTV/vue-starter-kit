<?php

namespace App\Actions\Roles;

use App\Actions\Activity\RecordActivity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

            $actor = Auth::user();
            app(RecordActivity::class)->handle('roles', (string) $role->getKey(), 'created', [
                'name' => ['before' => null, 'after' => $role->name],
                'permissions' => ['before' => null, 'after' => $role->permissions()->orderBy('name')->pluck('name')->all()],
            ], $actor instanceof User ? $actor : null);

            return $role;
        });
    }
}
