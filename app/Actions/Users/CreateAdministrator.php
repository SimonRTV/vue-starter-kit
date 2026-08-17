<?php

namespace App\Actions\Users;

use App\Actions\Permissions\SyncPolicyPermissions;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\DB;

final class CreateAdministrator
{
    public function __construct(
        private SyncPolicyPermissions $syncPolicyPermissions,
        private RecordUserManagementEvent $recordEvent,
    ) {}

    /**
     * Create a verified administrator with every policy-defined permission.
     *
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        $this->syncPolicyPermissions->handle();

        return DB::transaction(function () use ($attributes): User {
            $administrator = User::query()->create([
                ...$attributes,
                'email_verified_at' => now(),
            ]);
            $administrator->assignRole(RolePolicy::ADMINISTRATOR_ROLE);
            $this->recordEvent->handle(
                null,
                $administrator,
                'created',
                __('Created the account and assigned its initial access.'),
                ['roles' => [RolePolicy::ADMINISTRATOR_ROLE]],
            );

            return $administrator;
        });
    }
}
