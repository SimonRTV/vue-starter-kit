<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DisableUser
{
    public function __construct(
        private EnsureAdministratorContinuity $administratorContinuity,
        private RevokeUserSessions $revokeUserSessions,
        private RecordUserManagementEvent $recordEvent,
    ) {}

    public function handle(User $user, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->whereKey($user->getKey())
                ->firstOrFail();

            if ($lockedUser->disabled_at !== null) {
                return $lockedUser;
            }

            $this->administratorContinuity->disabling($lockedUser);
            $lockedUser->forceFill(['disabled_at' => now()])->save();
            $this->revokeUserSessions->handle($lockedUser);
            $this->recordEvent->handle(
                $actor,
                $lockedUser,
                'disabled',
                __('Disabled the account and revoked its sessions.'),
            );

            return $lockedUser->refresh();
        });
    }
}
