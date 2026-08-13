<?php

namespace Tests\Feature;

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
            'test@example.com' => 'Test User',
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
}
