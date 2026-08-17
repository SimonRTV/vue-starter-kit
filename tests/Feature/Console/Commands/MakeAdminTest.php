<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use App\Policies\PagePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MakeAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_creates_a_verified_administrator_with_every_policy_permission(): void
    {
        $password = 'Correct Horse Battery Staple!1';

        $this->artisan('make:admin')
            ->expectsQuestion('Name', '  Ada   Lovelace  ')
            ->expectsQuestion('Email', ' ADA@Example.com ')
            ->expectsQuestion('Password', $password)
            ->expectsOutputToContain('ada@example.com')
            ->assertSuccessful();

        $administrator = User::query()
            ->where('email', 'ada@example.com')
            ->sole();
        $policyPermissions = collect([
            ...PagePolicy::PERMISSIONS,
            ...RolePolicy::PERMISSIONS,
            ...UserPolicy::PERMISSIONS,
        ])->unique()->sort()->values()->all();

        $this->assertSame('Ada Lovelace', $administrator->name);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertTrue(Hash::check($password, $administrator->password));
        $this->assertTrue($administrator->hasRole(RolePolicy::ADMINISTRATOR_ROLE));
        $this->assertTrue($administrator->hasAllPermissions($policyPermissions));
        $this->assertSame(
            count($policyPermissions),
            Permission::query()->where('guard_name', 'web')->count(),
        );
        $this->assertDatabaseHas('user_management_events', [
            'actor_id' => null,
            'user_id' => $administrator->id,
            'action' => 'created',
        ]);
    }

    public function test_it_reprompts_for_an_email_that_is_already_in_use(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->artisan('make:admin')
            ->expectsQuestion('Name', 'Grace Hopper')
            ->expectsQuestion('Email', ' EXISTING@EXAMPLE.COM ')
            ->expectsQuestion('Email', 'grace@example.com')
            ->expectsQuestion('Password', 'Correct Horse Battery Staple!1')
            ->assertSuccessful();

        $this->assertSame(1, User::query()
            ->where('email', $existingUser->email)
            ->count());
        $this->assertTrue(User::query()
            ->where('email', 'grace@example.com')
            ->sole()
            ->hasRole(RolePolicy::ADMINISTRATOR_ROLE));
    }

    public function test_it_reprompts_until_the_password_is_valid(): void
    {
        $password = 'Correct Horse Battery Staple!1';

        $this->artisan('make:admin')
            ->expectsQuestion('Name', 'Katherine Johnson')
            ->expectsQuestion('Email', 'katherine@example.com')
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Password', $password)
            ->assertSuccessful();

        $administrator = User::query()
            ->where('email', 'katherine@example.com')
            ->sole();

        $this->assertTrue(Hash::check($password, $administrator->password));
    }

    public function test_it_fails_cleanly_when_input_is_not_interactive(): void
    {
        $this->artisan('make:admin', ['--no-interaction' => true])
            ->expectsOutputToContain('must be run interactively')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
