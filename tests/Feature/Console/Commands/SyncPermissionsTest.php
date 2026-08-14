<?php

namespace Tests\Feature\Console\Commands;

use App\Policies\PagePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SyncPermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_discovers_policy_permissions_and_preserves_existing_permissions(): void
    {
        $policyPermissions = collect([
            ...PagePolicy::PERMISSIONS,
            ...UserPolicy::PERMISSIONS,
            ...RolePolicy::PERMISSIONS,
        ])->unique()->sort()->values()->all();
        $externalPermission = Permission::query()->create([
            'name' => 'external.service.use',
            'guard_name' => 'web',
        ]);
        $administrator = Role::query()->create([
            'name' => RolePolicy::ADMINISTRATOR_ROLE,
            'guard_name' => 'web',
        ]);
        $administrator->givePermissionTo($externalPermission);

        $this->artisan('permissions:sync')
            ->expectsOutputToContain('Synchronized')
            ->expectsOutputToContain('Orphaned permissions: 1')
            ->expectsOutputToContain('external.service.use')
            ->expectsOutputToContain('No orphaned permissions were deleted.')
            ->assertSuccessful();

        $this->assertGreaterThanOrEqual(
            count($policyPermissions) + 1,
            Permission::query()->where('guard_name', 'web')->count(),
        );

        foreach ($policyPermissions as $policyPermission) {
            $this->assertTrue(
                Permission::query()
                    ->where('name', $policyPermission)
                    ->where('guard_name', 'web')
                    ->exists(),
            );
        }

        $administrator->refresh();

        $this->assertTrue($administrator->hasAllPermissions($policyPermissions));
        $this->assertTrue($administrator->hasPermissionTo($externalPermission));
    }

    public function test_it_is_idempotent(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();

        $permissionCount = Permission::query()->count();

        $this->artisan('permissions:sync')
            ->expectsOutputToContain('Created: 0')
            ->expectsOutputToContain('Granted to Administrator: 0')
            ->assertSuccessful();

        $this->assertSame($permissionCount, Permission::query()->count());
        $this->assertSame(1, Role::query()
            ->where('name', RolePolicy::ADMINISTRATOR_ROLE)
            ->where('guard_name', 'web')
            ->count());
    }

    public function test_dry_run_reports_missing_and_orphaned_permissions_without_writing(): void
    {
        $declaredPermissionCount = collect([
            ...PagePolicy::PERMISSIONS,
            ...RolePolicy::PERMISSIONS,
            ...UserPolicy::PERMISSIONS,
        ])->unique()->count();
        $orphanedPermission = Permission::query()->create([
            'name' => 'legacy.export',
            'guard_name' => 'web',
        ]);

        $this->artisan('permissions:sync', ['--dry-run' => true])
            ->expectsOutputToContain('No changes were made.')
            ->expectsOutputToContain(sprintf(
                'Missing permissions: %d',
                $declaredPermissionCount,
            ))
            ->expectsOutputToContain(sprintf(
                'Would grant to Administrator: %d',
                $declaredPermissionCount,
            ))
            ->expectsOutputToContain('Orphaned permissions: 1')
            ->expectsOutputToContain($orphanedPermission->name)
            ->assertSuccessful();

        $this->assertSame(1, Permission::query()->count());
        $this->assertModelExists($orphanedPermission);
        $this->assertFalse(Role::query()
            ->where('name', RolePolicy::ADMINISTRATOR_ROLE)
            ->where('guard_name', 'web')
            ->exists());
    }
}
