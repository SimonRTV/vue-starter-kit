<?php

namespace Database\Seeders;

use App\Actions\Permissions\SyncPolicyPermissions;
use Illuminate\Database\Seeder;

class UserManagementSeeder extends Seeder
{
    public function __construct(
        private SyncPolicyPermissions $syncPolicyPermissions,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->syncPolicyPermissions->handle();
    }
}
