<?php

namespace App\Actions\Users;

use App\Actions\Activity\RecordActivity;
use App\Actions\Notifications\SendNotification;
use App\Models\User;
use App\Models\UserManagementEvent;
use Illuminate\Support\Facades\DB;

class RecordUserManagementEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        ?User $actor,
        User $user,
        string $action,
        string $description,
        array $metadata = [],
    ): UserManagementEvent {
        return DB::transaction(function () use ($actor, $user, $action, $description, $metadata): UserManagementEvent {
            app(RecordActivity::class)->handle('users', (string) $user->getKey(), $action, [], $actor);

            $message = match ($action) {
                'invitation_sent' => ['Bienvenue dans votre espace', 'Votre compte est prêt. Complétez votre profil pour commencer.'],
                'updated' => ['Votre compte a été modifié', 'Les informations ou les accès de votre compte ont été mis à jour.'],
                'security_reset' => ['Votre sécurité a été réinitialisée', 'Un administrateur a réinitialisé les paramètres de sécurité de votre compte.'],
                'enabled' => ['Votre compte a été réactivé', 'Vous pouvez à nouveau accéder à votre espace.'],
                default => null,
            };
            if ($message !== null) {
                app(SendNotification::class)->handle(
                    $user, 'account', $message[0], $message[1], route('profile.edit', absolute: false), email: $action !== 'invitation_sent',
                );
            }

            return UserManagementEvent::query()->create([
                'actor_id' => $actor?->getKey(),
                'user_id' => $user->getKey(),
                'actor_name' => $actor?->name,
                'actor_email' => $actor?->email,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);
        });
    }
}
