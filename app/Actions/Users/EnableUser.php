<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnableUser
{
    public function __construct(
        private RecordUserManagementEvent $recordEvent,
    ) {}

    public function handle(User $user, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->whereKey($user->getKey())
                ->firstOrFail();

            if ($lockedUser->disabled_at === null) {
                return $lockedUser;
            }

            $lockedUser->forceFill(['disabled_at' => null])->save();
            $this->recordEvent->handle(
                $actor,
                $lockedUser,
                'enabled',
                __('Re-enabled the account.'),
            );

            return $lockedUser->refresh();
        });
    }
}
