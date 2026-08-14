<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserPasswordSetup;
use Illuminate\Support\Facades\Password;

class SendUserPasswordSetupLink
{
    public function __construct(
        private RecordUserManagementEvent $recordEvent,
    ) {}

    public function handle(User $user, ?User $actor = null, bool $invitation = false): void
    {
        $token = Password::broker()->createToken($user);

        if ($invitation) {
            $user->forceFill(['invitation_sent_at' => now()])->saveQuietly();
        }

        $user->notify(new UserPasswordSetup($token, $invitation));

        $this->recordEvent->handle(
            $actor,
            $user,
            $invitation ? 'invitation_sent' : 'password_reset_sent',
            $invitation
                ? __('Sent an account invitation.')
                : __('Sent a password reset link.'),
        );
    }
}
