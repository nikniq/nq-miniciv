<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A one-time sign-in link; the only thing a player needs to claim an account. */
class MagicLink extends Notification
{
    use Queueable;

    public function __construct(public string $url, public bool $isNew) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject($this->isNew ? "Confirm your email for {$app}" : "Your {$app} sign-in link")
            ->greeting('Greetings, ruler.')
            ->line($this->isNew
                ? 'Press the button to confirm your email and save your empire. No password needed.'
                : 'Press the button to sign in. No password needed.')
            ->action($this->isNew ? 'Confirm and play' : 'Sign in', $this->url)
            ->line('The link works once and expires in '.config('auth.magic_link_minutes', 30).' minutes. If you did not ask for it, ignore this email.');
    }
}
