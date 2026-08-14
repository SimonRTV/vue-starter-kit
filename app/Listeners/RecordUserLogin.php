<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordUserLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || $event->user->disabled_at !== null) {
            return;
        }

        User::withoutTimestamps(function () use ($event): void {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });
    }
}
