<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\InboxEmail;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SendNotification
{
    public function __construct(private NotificationPreferences $preferences) {}

    public function handle(User $user, string $category, string $title, string $body, ?string $actionPath = null, bool $email = true): ?DatabaseNotification
    {
        if (! array_key_exists($category, config('notifications.categories', []))) {
            throw new InvalidArgumentException('Unknown notification category.');
        }
        if ($actionPath !== null && (! str_starts_with($actionPath, '/') || str_starts_with($actionPath, '//') || preg_match('/[\\\\\x00-\x20]/', $actionPath))) {
            throw new InvalidArgumentException('Notification actions must be local paths.');
        }
        if (! config('starter.features.notifications')) {
            return null;
        }

        return DB::transaction(function () use ($user, $category, $title, $body, $actionPath, $email): DatabaseNotification {
            $notification = $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'workspace',
                'data' => ['category' => $category, 'title' => $title, 'body' => $body, 'action_path' => $actionPath],
                'read_at' => null,
            ]);
            if ($email && $this->preferences->wantsEmail($user, $category)) {
                $user->notify(new InboxEmail($notification->id, $category));
            }

            return $notification;
        });
    }
}
