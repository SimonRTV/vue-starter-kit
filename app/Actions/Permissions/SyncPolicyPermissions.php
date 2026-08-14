<?php

namespace App\Actions\Permissions;

use App\Policies\RolePolicy;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class SyncPolicyPermissions
{
    public function __construct(
        private DiscoverPolicyPermissions $discoverPolicyPermissions,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(bool $dryRun = false): PermissionSyncResult
    {
        $catalog = $this->discoverPolicyPermissions->handle();
        $permissionNames = $catalog->names();
        $existingPermissionNames = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->all();
        $missingPermissionNames = array_values(array_diff(
            $permissionNames,
            $existingPermissionNames,
        ));
        $orphanedPermissionNames = array_values(array_diff(
            $existingPermissionNames,
            $permissionNames,
        ));
        $administrator = Role::query()
            ->where('name', RolePolicy::ADMINISTRATOR_ROLE)
            ->where('guard_name', 'web')
            ->first();
        $administratorPermissionNames = $administrator?->permissions()
            ->pluck('name')
            ->all() ?? [];
        $administratorMissingPermissionNames = array_values(array_diff(
            $permissionNames,
            $administratorPermissionNames,
        ));

        if ($dryRun) {
            return new PermissionSyncResult(
                dryRun: true,
                createdPermissions: 0,
                existingPermissions: count($permissionNames) - count($missingPermissionNames),
                grantedToAdministrator: 0,
                policyClasses: $catalog->policyClasses,
                skippedPolicyClasses: $catalog->skippedPolicyClasses,
                missingPermissionNames: $missingPermissionNames,
                orphanedPermissionNames: $orphanedPermissionNames,
                administratorMissingPermissionNames: $administratorMissingPermissionNames,
            );
        }

        $this->permissionRegistrar->forgetCachedPermissions();

        $result = DB::transaction(function () use (
            $permissionNames,
            $catalog,
            $missingPermissionNames,
            $orphanedPermissionNames,
            $administratorMissingPermissionNames,
        ): PermissionSyncResult {
            $createdPermissions = 0;

            foreach ($permissionNames as $permissionName) {
                $permission = Permission::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);

                if ($permission->wasRecentlyCreated) {
                    $createdPermissions++;
                }
            }

            $administrator = Role::query()->firstOrCreate([
                'name' => RolePolicy::ADMINISTRATOR_ROLE,
                'guard_name' => 'web',
            ]);
            if ($permissionNames !== []) {
                $administrator->givePermissionTo($permissionNames);
            }

            return new PermissionSyncResult(
                dryRun: false,
                createdPermissions: $createdPermissions,
                existingPermissions: count($permissionNames) - $createdPermissions,
                grantedToAdministrator: count($administratorMissingPermissionNames),
                policyClasses: $catalog->policyClasses,
                skippedPolicyClasses: $catalog->skippedPolicyClasses,
                missingPermissionNames: $missingPermissionNames,
                orphanedPermissionNames: $orphanedPermissionNames,
                administratorMissingPermissionNames: $administratorMissingPermissionNames,
            );
        });

        $this->permissionRegistrar->forgetCachedPermissions();

        return $result;
    }
}
