<?php

namespace App\Notifications;

use App\Actions\Notifications\NotificationPreferences;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InboxEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public string $notificationId, public string $category)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User
            && app(NotificationPreferences::class)->wantsEmail($notifiable, $this->category)
            && $notifiable->notifications()->whereKey($this->notificationId)->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Une nouvelle notification vous attend')
            ->line('Vous avez reçu une nouvelle notification dans votre espace personnel.')
            ->action('Consulter mes notifications', route('notifications.index'));
    }
}
