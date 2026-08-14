<?php

namespace App\Actions\Fortify;

use App\Actions\Users\RecordUserManagementEvent;
use App\Actions\Users\RevokeUserSessions;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private RevokeUserSessions $revokeUserSessions,
        private RecordUserManagementEvent $recordEvent,
    ) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
        $this->revokeUserSessions->handle($user);
        $this->recordEvent->handle(
            $user,
            $user,
            'password_reset',
            __('Reset the account password and revoked all sessions.'),
        );
    }
}
