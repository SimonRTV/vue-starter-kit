<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPasswordSetup extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $token,
        public bool $invitation = false,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $email = is_string($notifiable->email ?? null) ? $notifiable->email : '';
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ]);
        $expiration = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject($this->invitation ? __('Set up your account') : __('Reset your password'))
            ->greeting($this->invitation ? __('Welcome!') : __('Hello!'))
            ->line($this->invitation
                ? __('An account has been created for you. Use the button below to choose your password.')
                : __('An administrator sent you a secure link to choose a new password.'))
            ->action($this->invitation ? __('Set up account') : __('Choose a new password'), $url)
            ->line(trans_choice(
                'This link expires in :count minute.|This link expires in :count minutes.',
                $expiration,
                ['count' => $expiration],
            ))
            ->line(__('If you were not expecting this message, contact an administrator.'));
    }
}
