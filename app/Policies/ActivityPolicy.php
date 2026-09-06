<?php

namespace App\Policies;

use App\Models\User;

class ActivityPolicy
{
    public const VIEW = 'activity.view';

    /** @var list<string> */
    public const PERMISSIONS = [self::VIEW];

    /** @var array<string, string> */
    public const PERMISSION_DESCRIPTIONS = [self::VIEW => 'Consulter le journal d’activité de toute l’application.'];

    /** @var list<string> */
    public const SENSITIVE_PERMISSIONS = [self::VIEW];

    public function viewAny(User $user): bool
    {
        return $user->can(self::VIEW);
    }
}
