<?php

namespace Tests\Feature;

use App\Actions\Users\DeleteUser;
use App\Actions\Users\DisableUser;
use App\Actions\Users\UpdateUser;
use App\Models\User;
use App\Notifications\UserPasswordSetup;
use App\Policies\PagePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_user_management(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_user_navigation_ability_is_shared_from_the_server(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageUsers', false),
            );

        $this->grantPermissions($actor, [UserPolicy::VIEW]);

        $this->actingAs($actor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageUsers', true),
            );
    }

    public function test_authorized_users_can_view_the_user_list(): void
    {
        $actor = User::factory()->create([
            'created_at' => now()->subDays(3),
        ]);
        $this->grantPermissions($actor, UserPolicy::PERMISSIONS);
        $editor = $this->role('Editor');
        $olderUser = User::factory()->unverified()->create([
            'name' => 'Older user',
            'created_at' => now()->subDays(2),
        ]);
        $olderUser->assignRole($editor);
        $newerUser = User::factory()->create([
            'name' => 'Newer user',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Index')
                ->has('users.data', 3)
                ->where('users.data.0.id', $newerUser->id)
                ->where('users.data.1.id', $olderUser->id)
                ->where('users.data.1.roles.0', 'Editor')
                ->where('users.data.1.email_verified_at', null)
                ->where('users.data.1.can.update', true)
                ->where('users.data.1.can.delete', true)
                ->where('users.total', 3)
                ->where('filters.search', null)
                ->where('filters.role', null)
                ->where('filters.verification', null)
                ->where('filters.status', null)
                ->where('filters.sort', 'created_at')
                ->where('filters.direction', 'desc')
                ->where('filters.per_page', 10)
                ->where('abilities.create', true)
                ->where('roles.0.name', 'Editor')
                ->missing('users.data.0.password'),
            );
    }

    public function test_user_list_can_be_searched_filtered_and_sorted_on_the_server(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, UserPolicy::PERMISSIONS);
        $editor = $this->role('Editor');
        $matchingUser = User::factory()->create([
            'name' => 'Alpha Manager',
            'email' => 'alpha@example.com',
        ]);
        $matchingUser->assignRole($editor);
        $unverifiedUser = User::factory()->unverified()->create([
            'name' => 'Alpha Editor',
            'email' => 'unverified-alpha@example.com',
        ]);
        $unverifiedUser->assignRole($editor);
        User::factory()->create([
            'name' => 'Beta Manager',
            'email' => 'beta@example.com',
        ]);

        $this->actingAs($actor)
            ->get(route('users.index', [
                'search' => '  Alpha   ',
                'role' => 'Editor',
                'verification' => 'verified',
                'status' => 'active',
                'sort' => 'name',
                'direction' => 'asc',
                'per_page' => 25,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.id', $matchingUser->id)
                ->where('users.total', 1)
                ->where('filters.search', 'Alpha')
                ->where('filters.role', 'Editor')
                ->where('filters.verification', 'verified')
                ->where('filters.status', 'active')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 25),
            );
    }

    public function test_user_list_is_paginated_by_the_server(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::VIEW]);

        User::factory()
            ->count(12)
            ->sequence(fn (Sequence $sequence): array => [
                'name' => sprintf('Member %02d', $sequence->index),
            ])
            ->create();

        $this->actingAs($actor)
            ->get(route('users.index', [
                'search' => 'Member',
                'sort' => 'name',
                'direction' => 'asc',
                'per_page' => 10,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 2)
                ->where('users.data.0.name', 'Member 10')
                ->where('users.data.1.name', 'Member 11')
                ->where('users.current_page', 2)
                ->where('users.last_page', 2)
                ->where('users.total', 12),
            );
    }

    public function test_user_list_rejects_unsupported_query_parameters(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::VIEW]);

        $this->actingAs($actor)
            ->getJson(route('users.index', [
                'role' => 'Missing role',
                'verification' => 'pending',
                'status' => 'pending',
                'sort' => 'password',
                'direction' => 'sideways',
                'per_page' => 500,
                'page' => 0,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
                'verification',
                'status',
                'sort',
                'direction',
                'per_page',
                'page',
            ]);
    }

    public function test_authorized_users_can_view_the_create_form(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::CREATE]);
        $this->role('Editor');

        $this->actingAs($actor)
            ->get(route('users.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Create')
                ->where('roles.0.name', 'Editor')
                ->where('roles.0.can_assign', false)
                ->where('canManageVerification', false),
            );
    }

    public function test_authorized_users_can_create_a_user_with_roles(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $this->grantPermissions($actor, [
            UserPolicy::CREATE,
            UserPolicy::ASSIGN_ROLES,
        ]);
        $this->role('Editor');

        $response = $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'Managed User',
            'email' => 'managed@example.com',
            'email_verified' => false,
            'roles' => ['Editor'],
        ]);

        $managedUser = User::query()->where('email', 'managed@example.com')->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.show', $managedUser));

        $this->assertSame('Managed User', $managedUser->name);
        $this->assertNull($managedUser->email_verified_at);
        $this->assertNotNull($managedUser->invitation_sent_at);
        $this->assertTrue($managedUser->hasRole('Editor'));
        Notification::assertSentTo(
            $managedUser,
            fn (UserPasswordSetup $notification): bool => $notification->invitation,
        );
        $this->assertDatabaseHas('user_management_events', [
            'actor_id' => $actor->id,
            'user_id' => $managedUser->id,
            'action' => 'created',
        ]);
        $this->assertDatabaseHas('user_management_events', [
            'actor_id' => $actor->id,
            'user_id' => $managedUser->id,
            'action' => 'invitation_sent',
        ]);
    }

    public function test_users_without_role_assignment_permission_cannot_assign_roles(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::CREATE]);
        $this->role('Editor');

        $this->actingAs($actor)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Managed User',
                'email' => 'managed@example.com',
                'email_verified' => false,
                'roles' => ['Editor'],
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('roles');

        $this->assertFalse(User::query()
            ->where('email', 'managed@example.com')
            ->exists());
    }

    public function test_users_cannot_assign_roles_that_grant_permissions_they_do_not_have(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [
            UserPolicy::CREATE,
            UserPolicy::ASSIGN_ROLES,
            PagePolicy::VIEW,
        ]);
        $this->role('Page viewer', [PagePolicy::VIEW]);
        $this->role('User viewer', [UserPolicy::VIEW]);

        $this->actingAs($actor)
            ->post(route('users.store'), [
                'name' => 'Page User',
                'email' => 'page-user@example.com',
                'email_verified' => false,
                'roles' => ['Page viewer'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::query()
            ->where('email', 'page-user@example.com')
            ->sole()
            ->hasRole('Page viewer'));

        $this->actingAs($actor)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'User Manager',
                'email' => 'user-manager@example.com',
                'email_verified' => false,
                'roles' => ['User viewer'],
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('roles');
    }

    public function test_only_administrators_can_assign_the_administrator_role(): void
    {
        $administrator = $this->role(
            RolePolicy::ADMINISTRATOR_ROLE,
            UserPolicy::PERMISSIONS,
        );
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [
            UserPolicy::CREATE,
            UserPolicy::ASSIGN_ROLES,
            UserPolicy::ASSIGN_ADMINISTRATOR,
        ]);

        $this->actingAs($actor)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'New Administrator',
                'email' => 'new-administrator@example.com',
                'email_verified' => false,
                'roles' => [$administrator->name],
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('roles');

        $actor->assignRole($administrator);

        $this->actingAs($actor)
            ->post(route('users.store'), [
                'name' => 'New Administrator',
                'email' => 'new-administrator@example.com',
                'email_verified' => false,
                'roles' => [$administrator->name],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::query()
            ->where('email', 'new-administrator@example.com')
            ->sole()
            ->hasRole($administrator));
    }

    public function test_users_cannot_remove_roles_that_grant_permissions_they_do_not_have(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [
            UserPolicy::UPDATE,
            UserPolicy::ASSIGN_ROLES,
            PagePolicy::VIEW,
        ]);
        $lockedRole = $this->role('User viewer', [UserPolicy::VIEW]);
        $managedUser = User::factory()->create();
        $managedUser->assignRole($lockedRole);

        $this->actingAs($actor)
            ->from(route('users.edit', $managedUser))
            ->patch(route('users.update', $managedUser), [
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'email_verified' => true,
                'roles' => [],
            ])
            ->assertRedirect(route('users.edit', $managedUser))
            ->assertSessionHasErrors('roles');

        $this->assertTrue($managedUser->refresh()->hasRole($lockedRole));
    }

    public function test_user_creation_validates_identity_verification_and_roles(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::CREATE]);

        $this->actingAs($actor)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => '',
                'email' => $actor->email,
                'email_verified' => 'sometimes',
                'roles' => ['Missing role'],
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'name',
                'email',
                'email_verified',
                'roles.0',
            ]);
    }

    public function test_authorized_users_can_view_a_user_and_their_effective_permissions(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::VIEW]);
        $editor = $this->role('Editor', [UserPolicy::VIEW]);
        $managedUser = User::factory()->create();
        $managedUser->assignRole($editor);

        $this->actingAs($actor)
            ->get(route('users.show', $managedUser))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Show')
                ->where('user.id', $managedUser->id)
                ->where('user.roles.0', 'Editor')
                ->where('user.permissions.0', UserPolicy::VIEW)
                ->where('user.can.update', false)
                ->where('user.can.delete', false),
            );
    }

    public function test_authorized_users_can_update_identity_verification_and_roles(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, UserPolicy::PERMISSIONS);
        $editor = $this->role('Editor');
        $manager = $this->role('Manager');
        $managedUser = User::factory()->create();
        $existingPassword = $managedUser->password;
        $managedUser->assignRole($editor);

        $response = $this->actingAs($actor)->patch(route('users.update', $managedUser), [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'email_verified' => false,
            'roles' => [$manager->name],
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.show', $managedUser));

        $managedUser->refresh();

        $this->assertSame('Updated User', $managedUser->name);
        $this->assertSame('updated@example.com', $managedUser->email);
        $this->assertSame($existingPassword, $managedUser->password);
        $this->assertNull($managedUser->email_verified_at);
        $this->assertTrue($managedUser->hasExactRoles('Manager'));
    }

    public function test_updating_a_user_without_a_password_preserves_the_existing_password(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::UPDATE]);
        $managedUser = User::factory()->create();
        $existingPassword = $managedUser->password;
        $existingVerifiedAt = $managedUser->email_verified_at;

        $this->assertNotNull($existingVerifiedAt);

        $this->actingAs($actor)->patch(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'email_verified' => true,
            'roles' => [],
        ])->assertSessionHasNoErrors();

        $managedUser->refresh();

        $this->assertSame($existingPassword, $managedUser->password);
        $this->assertNotNull($managedUser->email_verified_at);
        $this->assertTrue(
            $existingVerifiedAt->equalTo($managedUser->email_verified_at),
        );
    }

    public function test_users_cannot_change_their_own_roles(): void
    {
        $administrator = $this->role('Administrator', UserPolicy::PERMISSIONS);
        $actor = User::factory()->create();
        $actor->assignRole($administrator);

        $this->actingAs($actor)
            ->from(route('users.edit', $actor))
            ->patch(route('users.update', $actor), [
                'name' => $actor->name,
                'email' => $actor->email,
                'email_verified' => true,
                'roles' => [],
            ])
            ->assertRedirect(route('users.edit', $actor))
            ->assertSessionHasErrors('roles');

        $this->assertTrue($actor->refresh()->hasRole('Administrator'));
    }

    public function test_non_administrators_cannot_modify_administrator_accounts(): void
    {
        $administratorRole = $this->role(
            RolePolicy::ADMINISTRATOR_ROLE,
            UserPolicy::PERMISSIONS,
        );
        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::UPDATE, UserPolicy::DELETE]);

        $this->actingAs($actor)
            ->patch(route('users.update', $administrator), [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'email_verified' => true,
                'roles' => [$administratorRole->name],
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->delete(route('users.destroy', $administrator))
            ->assertForbidden();

        $this->assertModelExists($administrator);
    }

    public function test_the_final_verified_administrator_cannot_be_unverified(): void
    {
        $administratorRole = $this->role(
            RolePolicy::ADMINISTRATOR_ROLE,
            UserPolicy::PERMISSIONS,
        );
        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);

        $this->actingAs($administrator)
            ->from(route('users.edit', $administrator))
            ->patch(route('users.update', $administrator), [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'email_verified' => false,
                'roles' => [$administratorRole->name],
            ])
            ->assertRedirect(route('users.edit', $administrator))
            ->assertSessionHasErrors('email_verified');

        $this->assertNotNull($administrator->refresh()->email_verified_at);
    }

    public function test_an_administrator_can_demote_another_administrator_when_one_remains(): void
    {
        $administratorRole = $this->role(
            RolePolicy::ADMINISTRATOR_ROLE,
            UserPolicy::PERMISSIONS,
        );
        $actor = User::factory()->create();
        $actor->assignRole($administratorRole);
        $managedAdministrator = User::factory()->create();
        $managedAdministrator->assignRole($administratorRole);

        $this->actingAs($actor)
            ->patch(route('users.update', $managedAdministrator), [
                'name' => $managedAdministrator->name,
                'email' => $managedAdministrator->email,
                'email_verified' => true,
                'roles' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($managedAdministrator->refresh()->hasRole($administratorRole));
        $this->assertTrue($actor->refresh()->hasRole($administratorRole));
    }

    public function test_the_final_verified_administrator_is_protected_inside_user_actions(): void
    {
        $administratorRole = $this->role(
            RolePolicy::ADMINISTRATOR_ROLE,
            UserPolicy::PERMISSIONS,
        );
        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);

        try {
            app(UpdateUser::class)->handle(
                $administrator,
                [
                    'name' => $administrator->name,
                    'email' => $administrator->email,
                    'email_verified_at' => $administrator->email_verified_at,
                ],
                [],
            );

            self::fail('The final Administrator role was removed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roles', $exception->errors());
        }

        try {
            app(DeleteUser::class)->handle($administrator);

            self::fail('The final Administrator was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $this->assertModelExists($administrator);
        $this->assertTrue($administrator->refresh()->hasRole($administratorRole));
    }

    public function test_authorized_users_can_disable_and_re_enable_an_account(): void
    {
        config(['session.driver' => 'database']);

        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::SUSPEND]);
        $managedUser = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'managed-user-session',
            'user_id' => $managedUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($actor)
            ->post(route('users.disable', $managedUser))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($managedUser->refresh()->disabled_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'managed-user-session']);
        $this->assertDatabaseHas('user_management_events', [
            'user_id' => $managedUser->id,
            'action' => 'disabled',
        ]);

        $this->actingAs($actor)
            ->delete(route('users.enable', $managedUser))
            ->assertSessionHasNoErrors();

        $this->assertNull($managedUser->refresh()->disabled_at);
        $this->assertDatabaseHas('user_management_events', [
            'user_id' => $managedUser->id,
            'action' => 'enabled',
        ]);
    }

    public function test_the_final_active_verified_administrator_cannot_be_disabled(): void
    {
        $administratorRole = $this->role(
            RolePolicy::ADMINISTRATOR_ROLE,
            UserPolicy::PERMISSIONS,
        );
        $administrator = User::factory()->create();
        $administrator->assignRole($administratorRole);

        try {
            app(DisableUser::class)->handle($administrator, $administrator);

            self::fail('The final active, verified Administrator was disabled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $otherAdministrator = User::factory()->create();
        $otherAdministrator->assignRole($administratorRole);

        $this->actingAs($administrator)
            ->post(route('users.disable', $otherAdministrator))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($otherAdministrator->refresh()->disabled_at);
    }

    public function test_authorized_users_can_send_a_password_reset_link(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::RESET_PASSWORD]);
        $managedUser = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('users.password-reset', $managedUser))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $managedUser,
            fn (UserPasswordSetup $notification): bool => ! $notification->invitation,
        );
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $managedUser->email,
        ]);
    }

    public function test_security_reset_revokes_credentials_and_sessions(): void
    {
        Notification::fake();
        config(['session.driver' => 'database']);

        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::RESET_SECURITY]);
        $managedUser = User::factory()->withTwoFactor()->create();
        DB::table('passkeys')->insert([
            'user_id' => $managedUser->id,
            'name' => 'Security key',
            'credential_id' => 'credential-'.$managedUser->id,
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'security-reset-session',
            'user_id' => $managedUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($actor)
            ->post(route('users.security-reset', $managedUser))
            ->assertSessionHasNoErrors();

        $managedUser->refresh();

        $this->assertNull($managedUser->two_factor_secret);
        $this->assertNull($managedUser->two_factor_recovery_codes);
        $this->assertNull($managedUser->two_factor_confirmed_at);
        $this->assertDatabaseMissing('passkeys', ['user_id' => $managedUser->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'security-reset-session']);
        $this->assertDatabaseHas('user_management_events', [
            'user_id' => $managedUser->id,
            'action' => 'security_reset',
        ]);
        Notification::assertSentTo($managedUser, UserPasswordSetup::class);
    }

    public function test_email_addresses_are_normalized_and_verification_changes_are_separately_authorized(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::CREATE, UserPolicy::UPDATE]);

        $this->actingAs($actor)
            ->post(route('users.store'), [
                'name' => 'Normalized User',
                'email' => '  Mixed.Case@Example.COM ',
                'email_verified' => false,
                'roles' => [],
            ])
            ->assertSessionHasNoErrors();

        $managedUser = User::query()->where('email', 'mixed.case@example.com')->sole();

        $this->actingAs($actor)
            ->from(route('users.edit', $managedUser))
            ->patch(route('users.update', $managedUser), [
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'email_verified' => true,
                'roles' => [],
            ])
            ->assertRedirect(route('users.edit', $managedUser))
            ->assertSessionHasErrors('email_verified');

        $this->assertNull($managedUser->refresh()->email_verified_at);
    }

    public function test_authorized_users_can_delete_another_user(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::DELETE]);
        $managedUser = User::factory()->disabled()->create();

        $this->actingAs($actor)
            ->delete(route('users.destroy', $managedUser))
            ->assertRedirect(route('users.index'));

        $this->assertModelMissing($managedUser);
    }

    public function test_users_cannot_delete_their_own_account_from_user_management(): void
    {
        $actor = User::factory()->create();
        $this->grantPermissions($actor, [UserPolicy::DELETE]);

        $this->actingAs($actor)
            ->delete(route('users.destroy', $actor))
            ->assertForbidden();

        $this->assertModelExists($actor);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantPermissions(User $user, array $permissions): void
    {
        $permissionModels = collect($permissions)
            ->map(static fn (string $permission): Permission => Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]));

        $user->givePermissionTo($permissionModels);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function role(string $name, array $permissions = []): Role
    {
        $role = Role::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        if ($permissions !== []) {
            $permissionModels = collect($permissions)
                ->map(static fn (string $permission): Permission => Permission::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]));

            $role->syncPermissions($permissionModels);
        }

        return $role;
    }
}
