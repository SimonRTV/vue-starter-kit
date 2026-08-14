<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserManagementEvent;

class RecordUserManagementEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        ?User $actor,
        User $user,
        string $action,
        string $description,
        array $metadata = [],
    ): UserManagementEvent {
        return UserManagementEvent::query()->create([
            'actor_id' => $actor?->getKey(),
            'user_id' => $user->getKey(),
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
