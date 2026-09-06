<?php

namespace App\Actions\Notifications;

use App\Models\User;

class NotificationPreferences
{
    /** @return array<string, bool> */
    public function get(User $user): array
    {
        $preferences = [];
        /** @var array<string, array{label: string, description: string}> $categories */
        $categories = config('notifications.categories', []);
        foreach (array_keys($categories) as $category) {
            $preferences[$category] = ($user->notification_preferences[$category] ?? false) === true;
        }

        return $preferences;
    }

    public function wantsEmail(User $user, string $category): bool
    {
        return config('starter.features.notifications') && $user->disabled_at === null
            && $user->hasVerifiedEmail() && ($this->get($user)[$category] ?? false);
    }
}
