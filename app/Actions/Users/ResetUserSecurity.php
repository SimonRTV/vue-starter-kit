<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetUserSecurity
{
    public function __construct(
        private RevokeUserSessions $revokeUserSessions,
        private SendUserPasswordSetupLink $sendPasswordSetupLink,
        private RecordUserManagementEvent $recordEvent,
    ) {}

    public function handle(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->whereKey($user->getKey())
                ->firstOrFail();

            $lockedUser->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
            $lockedUser->passkeys()->delete();
            $this->revokeUserSessions->handle($lockedUser);
            $this->sendPasswordSetupLink->handle($lockedUser, $actor);
            $this->recordEvent->handle(
                $actor,
                $lockedUser,
                'security_reset',
                __('Reset two-factor authentication and passkeys, revoked all sessions, and sent a password reset link.'),
            );
        });
    }
}
