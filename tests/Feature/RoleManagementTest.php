<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\PagePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_role_management(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_role_navigation_ability_is_shared_from_the_server(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageRoles', false),
            );

        $this->grantPermissions($actor, [RolePolicy::VIEW]);

        $this->actingAs($actor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageRoles', true),
            );
    }

    public function test_authorized_users_can_view_the_role_list_with_counts(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, RolePolicy::PERMISSIONS);
        $olderRole = $this->role('Editor', [RolePolicy::VIEW]);
        $olderRole->update(['created_at' => now()->subDays(2)]);
        $newerRole = $this->role('Reviewer');
        $newerRole->update(['created_at' => now()->subDay()]);
        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($olderRole);

        $this->actingAs($actor)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/Index')
                ->has('roles.data', 2)
                ->where('roles.data.0.id', $newerRole->id)
                ->where('roles.data.0.users_count', 0)
                ->where('roles.data.1.id', $olderRole->id)
                ->where('roles.data.1.users_count', 1)
                ->where('roles.data.1.permissions_count', 1)
                ->where('roles.data.1.can.update', true)
                ->where('roles.data.1.can.delete', false)
                ->where('roles.total', 2)
                ->where('filters.search', null)
                ->where('filters.assignment', null)
                ->where('filters.sort', 'created_at')
                ->where('filters.direction', 'desc')
                ->where('filters.per_page', 10)
                ->where('abilities.create', true),
            );
    }

    public function test_role_list_can_be_searched_filtered_and_sorted_on_the_server(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::VIEW]);
        $matchingRole = $this->role('Alpha editor');
        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($matchingRole);
        $this->role('Alpha reviewer');
        $this->role('Beta editor');

        $this->actingAs($actor)
            ->get(route('roles.index', [
                'search' => '  Alpha   ',
                'assignment' => 'assigned',
                'sort' => 'name',
                'direction' => 'asc',
                'per_page' => 25,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('roles.data', 1)
                ->where('roles.data.0.id', $matchingRole->id)
                ->where('roles.total', 1)
                ->where('filters.search', 'Alpha')
                ->where('filters.assignment', 'assigned')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 25),
            );
    }

    public function test_role_list_is_paginated_by_the_server(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::VIEW]);

        foreach (range(0, 11) as $index) {
            $this->role(sprintf('Team %02d', $index));
        }

        $this->actingAs($actor)
            ->get(route('roles.index', [
                'search' => 'Team',
                'sort' => 'name',
                'direction' => 'asc',
                'per_page' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('roles.data', 2)
                ->where('roles.data.0.name', 'Team 10')
                ->where('roles.data.1.name', 'Team 11')
                ->where('roles.current_page', 2)
                ->where('roles.last_page', 2)
                ->where('roles.total', 12),
            );
    }

    public function test_role_list_rejects_unsupported_query_parameters(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::VIEW]);

        $this->actingAs($actor)
            ->getJson(route('roles.index', [
                'assignment' => 'pending',
                'sort' => 'guard_name',
                'direction' => 'sideways',
                'per_page' => 500,
                'page' => 0,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assignment',
                'sort',
                'direction',
                'per_page',
                'page',
            ]);
    }

    public function test_authorized_users_can_view_the_create_form(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::CREATE]);
        $permission = $this->permission('articles.publish');

        $this->actingAs($actor)
            ->get(route('roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/Create')
                ->where('permissions.0.id', $permission->id)
                ->where('permissions.0.name', 'articles.publish')
                ->where('permissions.0.label', 'Publier')
                ->where('permissions.0.description', 'Cette autorisation n’est déclarée par aucune politique de l’application.')
                ->where('permissions.0.group', 'articles')
                ->where('permissions.0.group_label', 'Articles')
                ->where('permissions.0.is_sensitive', false)
                ->where('permissions.0.is_orphaned', true),
            );
    }

    public function test_role_forms_receive_declared_permission_metadata(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::CREATE]);
        $permission = $this->permission(PagePolicy::DELETE);

        $this->actingAs($actor)
            ->get(route('roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/Create')
                ->where('permissions.0.id', $permission->id)
                ->where('permissions.0.name', PagePolicy::DELETE)
                ->where('permissions.0.label', 'Supprimer')
                ->where('permissions.0.description', 'Supprimer, restaurer et supprimer définitivement des pages.')
                ->where('permissions.0.group', 'pages')
                ->where('permissions.0.group_label', 'Pages')
                ->where('permissions.0.is_sensitive', true)
                ->where('permissions.0.is_orphaned', false),
            );
    }

    public function test_authorized_users_can_create_a_role_with_permissions(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::CREATE]);
        $this->permission('articles.publish');
        $this->permission('articles.update');

        $response = $this->actingAs($actor)->post(route('roles.store'), [
            'name' => '  Content   editor  ',
            'permissions' => ['articles.publish', 'articles.update'],
        ]);

        $role = Role::query()->where('name', 'Content editor')->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('roles.show', $role));

        $this->assertTrue($role->hasAllPermissions([
            'articles.publish',
            'articles.update',
        ]));
    }

    public function test_role_creation_validates_name_and_permissions(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::CREATE]);
        $this->role('Editor');

        $this->actingAs($actor)
            ->from(route('roles.create'))
            ->post(route('roles.store'), [
                'name' => 'Editor',
                'permissions' => ['missing.permission'],
            ])
            ->assertRedirect(route('roles.create'))
            ->assertSessionHasErrors(['name', 'permissions.0']);

        $this->actingAs($actor)
            ->from(route('roles.create'))
            ->post(route('roles.store'), [
                'name' => '',
            ])
            ->assertRedirect(route('roles.create'))
            ->assertSessionHasErrors(['name', 'permissions']);
    }

    public function test_authorized_users_can_view_a_role_with_permissions_and_assigned_users(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [
            ...RolePolicy::PERMISSIONS,
            UserPolicy::VIEW,
        ]);
        $role = $this->role('Editor', [
            'articles.update',
            'pages.view',
            'articles.publish',
        ]);
        $assignedUser = User::factory()->create([
            'name' => 'Assigned user',
            'email' => 'assigned@example.com',
        ]);
        $assignedUser->assignRole($role);

        $this->actingAs($actor)
            ->get(route('roles.show', $role))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/Show')
                ->where('role.id', $role->id)
                ->where('role.permissions', [
                    'articles.publish',
                    'articles.update',
                    'pages.view',
                ])
                ->where('role.permissions_count', 3)
                ->where('role.users_count', 1)
                ->where('role.assigned_users.0.id', $assignedUser->id)
                ->where('role.assigned_users.0.email', 'assigned@example.com')
                ->where('role.can_view_assigned_users', true)
                ->where('role.assigned_to_current_user', false)
                ->where('role.can.update', true)
                ->where('role.can.delete', false),
            );
    }

    public function test_role_details_hide_user_identities_without_user_view_permission(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::VIEW]);
        $role = $this->role('Editor');
        User::factory()->create()->assignRole($role);

        $this->actingAs($actor)
            ->get(route('roles.show', $role))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('role.users_count', 1)
                ->where('role.can_view_assigned_users', false)
                ->where('role.assigned_users', []),
            );
    }

    public function test_authorized_users_can_update_a_role_and_its_permissions(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::UPDATE]);
        $role = $this->role('Editor', ['articles.update']);
        $this->permission('articles.publish');

        $this->actingAs($actor)
            ->patch(route('roles.update', $role), [
                'name' => 'Content editor',
                'permissions' => ['articles.publish'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('roles.show', $role));

        $role->refresh();

        $this->assertSame('Content editor', $role->name);
        $this->assertTrue($role->hasPermissionTo('articles.publish'));
        $this->assertFalse($role->hasPermissionTo('articles.update'));
    }

    public function test_users_cannot_update_a_role_assigned_to_their_own_account(): void
    {
        $role = $this->role('Role manager', [RolePolicy::UPDATE]);
        $actor = User::factory()->create();
        $actor->assignRole($role);

        $this->actingAs($actor)
            ->patch(route('roles.update', $role), [
                'name' => 'Changed role',
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->assertSame('Role manager', $role->refresh()->name);
    }

    public function test_the_administrator_role_cannot_be_updated_or_deleted(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::UPDATE, RolePolicy::DELETE]);
        $administrator = $this->role(RolePolicy::ADMINISTRATOR_ROLE);

        $this->actingAs($actor)
            ->patch(route('roles.update', $administrator), [
                'name' => 'Changed administrator',
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->delete(route('roles.destroy', $administrator))
            ->assertForbidden();

        $this->assertModelExists($administrator);
    }

    public function test_roles_assigned_to_users_cannot_be_deleted(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::DELETE]);
        $role = $this->role('Editor');
        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($role);

        $this->actingAs($actor)
            ->delete(route('roles.destroy', $role))
            ->assertForbidden();

        $this->assertModelExists($role);
        $this->assertTrue($assignedUser->refresh()->hasRole($role));
    }

    public function test_authorized_users_can_delete_an_unused_role(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::DELETE]);
        $role = $this->role('Unused role');

        $this->actingAs($actor)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertModelMissing($role);
    }

    public function test_roles_for_other_guards_are_not_exposed(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [RolePolicy::VIEW]);
        $webRole = $this->role('Web role');
        $apiRole = Role::query()->create([
            'name' => 'API role',
            'guard_name' => 'api',
        ]);

        $this->actingAs($actor)
            ->get(route('roles.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('roles.data', 1)
                ->where('roles.data.0.id', $webRole->id),
            );

        $this->actingAs($actor)
            ->get(route('roles.show', $apiRole))
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantPermissions(User $user, array $permissions): void
    {
        $permissionModels = collect($permissions)
            ->map(fn (string $permission): Permission => $this->permission($permission));

        $user->givePermissionTo($permissionModels);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function role(string $name, array $permissions = []): Role
    {
        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        if ($permissions !== []) {
            $permissionModels = collect($permissions)
                ->map(fn (string $permission): Permission => $this->permission($permission));

            $role->syncPermissions($permissionModels);
        }

        return $role;
    }

    private function permission(string $name): Permission
    {
        return Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }
}
