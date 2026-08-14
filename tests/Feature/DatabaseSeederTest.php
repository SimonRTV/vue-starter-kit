<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_sample_users_with_known_credentials(): void
    {
        $this->seed();

        $expectedUsers = [
            'test@example.com' => 'Utilisateur test',
            'alice@example.com' => 'Alice',
            'bob@example.com' => 'Bob',
            'charlie@example.com' => 'Charlie',
            'diana@example.com' => 'Diana',
            'ethan@example.com' => 'Ethan',
        ];

        $seededUsers = User::query()->get()->keyBy('email');

        $this->assertCount(count($expectedUsers), $seededUsers);

        foreach ($expectedUsers as $email => $name) {
            $seededUser = $seededUsers->get($email);

            $this->assertInstanceOf(User::class, $seededUser);
            $this->assertSame($name, $seededUser->name);
            $this->assertNotNull($seededUser->email_verified_at);
            $this->assertTrue(Hash::check('password', $seededUser->password));
            $this->assertNull($seededUser->two_factor_confirmed_at);
        }
    }

    public function test_it_creates_sample_pages(): void
    {
        $this->seed();

        $seededPages = Page::query()->get()->keyBy('slug');

        $this->assertCount(4, $seededPages);
        $this->assertTrue($seededPages->get('a-propos')->is_published);
        $this->assertNotNull($seededPages->get('a-propos')->published_at);
        $this->assertFalse($seededPages->get('services')->is_published);
        $this->assertNull($seededPages->get('services')->published_at);
    }

    public function test_it_grants_user_and_role_management_to_the_sample_administrator(): void
    {
        $this->seed();

        $administrator = User::query()->where('email', 'test@example.com')->sole();

        $this->assertTrue($administrator->hasRole('Administrator'));
        $this->assertTrue($administrator->can('users.view'));
        $this->assertTrue($administrator->can('users.create'));
        $this->assertTrue($administrator->can('users.update'));
        $this->assertTrue($administrator->can('users.delete'));
        $this->assertTrue($administrator->can('roles.view'));
        $this->assertTrue($administrator->can('roles.create'));
        $this->assertTrue($administrator->can('roles.update'));
        $this->assertTrue($administrator->can('roles.delete'));
    }
}
