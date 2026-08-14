<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function __construct(
        private EnsureAdministratorContinuity $administratorContinuity,
        private RevokeUserSessions $revokeUserSessions,
        private RecordUserManagementEvent $recordEvent,
    ) {}

    /**
     * Delete the user.
     */
    public function handle(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->whereKey($user->getKey())
                ->firstOrFail();

            $this->administratorContinuity->deleting($lockedUser);
            $this->revokeUserSessions->handle($lockedUser);
            $this->recordEvent->handle(
                $actor,
                $lockedUser,
                'deleted',
                __('Permanently deleted the account.'),
            );
            $lockedUser->delete();
        });
    }
}
