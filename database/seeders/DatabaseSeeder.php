<?php

namespace Database\Seeders;

use App\Models\User;
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
        $this->call(PageSeeder::class);

        $sampleUsers = [
            ['name' => 'Test User', 'email' => 'test@example.com'],
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
            ['name' => 'Charlie', 'email' => 'charlie@example.com'],
            ['name' => 'Diana', 'email' => 'diana@example.com'],
            ['name' => 'Ethan', 'email' => 'ethan@example.com'],
        ];

        foreach ($sampleUsers as $sampleUser) {
            User::factory()->create($sampleUser);
        }
    }
}
