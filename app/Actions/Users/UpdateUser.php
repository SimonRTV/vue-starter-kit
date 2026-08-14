<?php

namespace App\Actions\Users;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    public function __construct(
        private EnsureAdministratorContinuity $administratorContinuity,
        private RecordUserManagementEvent $recordEvent,
    ) {}

    /**
     * Update a user and synchronize their roles.
     *
     * @param  array{name: string, email: string, email_verified_at: CarbonInterface|null}  $attributes
     * @param  list<string>  $roleNames
     */
    public function handle(
        User $user,
        array $attributes,
        array $roleNames,
        ?User $actor = null,
        string $verificationErrorKey = 'email_verified',
    ): User {
        return DB::transaction(function () use ($user, $attributes, $roleNames, $actor, $verificationErrorKey): User {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->whereKey($user->getKey())
                ->firstOrFail();

            $this->administratorContinuity->updating(
                $lockedUser,
                $attributes,
                $roleNames,
                $verificationErrorKey,
            );
            $previousRoles = $lockedUser->roles()
                ->where('guard_name', 'web')
                ->pluck('name')
                ->sort()
                ->values()
                ->all();
            $lockedUser->update($attributes);
            $lockedUser->syncRoles($roleNames);

            $changedFields = array_keys($lockedUser->getChanges());
            $rolesChanged = $previousRoles !== collect($roleNames)->sort()->values()->all();

            if ($changedFields !== [] || $rolesChanged) {
                $this->recordEvent->handle(
                    $actor,
                    $lockedUser,
                    'updated',
                    __('Updated account details and access.'),
                    [
                        'fields' => array_values(array_diff($changedFields, ['updated_at'])),
                        'roles_changed' => $rolesChanged,
                    ],
                );
            }

            return $lockedUser->refresh();
        });
    }
}
