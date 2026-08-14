<?php

namespace Database\Seeders;

use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserManagementSeeder::class,
            ApplicationSettingSeeder::class,
            PageSeeder::class,
        ]);

        $sampleUsers = [
            ['name' => 'Utilisateur test', 'email' => 'test@example.com'],
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
            ['name' => 'Charlie', 'email' => 'charlie@example.com'],
            ['name' => 'Diana', 'email' => 'diana@example.com'],
            ['name' => 'Ethan', 'email' => 'ethan@example.com'],
        ];

        foreach ($sampleUsers as $sampleUser) {
            $user = User::factory()->create($sampleUser);

            if ($sampleUser['email'] === 'test@example.com') {
                $user->assignRole(RolePolicy::ADMINISTRATOR_ROLE);
            }
        }
    }
}
