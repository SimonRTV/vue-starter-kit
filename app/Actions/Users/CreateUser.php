<?php

namespace App\Actions\Users;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser
{
    public function __construct(
        private SendUserPasswordSetupLink $sendPasswordSetupLink,
        private RecordUserManagementEvent $recordEvent,
    ) {}

    /**
     * Create a user and assign their roles.
     *
     * @param  array{name: string, email: string, email_verified_at: CarbonInterface|null}  $attributes
     * @param  list<string>  $roleNames
     */
    public function handle(array $attributes, array $roleNames, ?User $actor = null): User
    {
        return DB::transaction(function () use ($attributes, $roleNames, $actor): User {
            $user = User::query()->create([
                ...$attributes,
                'password' => Str::password(40),
            ]);
            $user->syncRoles($roleNames);
            $this->recordEvent->handle(
                $actor,
                $user,
                'created',
                __('Created the account and assigned its initial access.'),
                ['roles' => $roleNames],
            );
            $this->sendPasswordSetupLink->handle($user, $actor, invitation: true);

            return $user;
        });
    }
}
