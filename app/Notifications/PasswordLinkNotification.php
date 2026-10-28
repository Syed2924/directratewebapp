<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordLinkNotification extends Notification
{
    public function __construct(public string $token, public bool $isSetup = false)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.users.expire');

        $mail = (new MailMessage)->greeting('Assalam-o-Alaikum ' . $notifiable->name . ',');

        if ($this->isSetup) {
            return $mail->subject('Set up your account — ' . config('app.name'))
                ->line('An account has been created for you on the ' . config('app.name') . ' Management System.')
                ->action('Set your password', $url)
                ->line("This link can be used once and expires in {$minutes} minutes. Ask your administrator for a new link if it expires.");
        }

        return $mail->subject('Password reset — ' . config('app.name'))
            ->line('A password reset was requested for your account.')
            ->action('Reset password', $url)
            ->line("This link can be used once and expires in {$minutes} minutes.")
            ->line('If you did not request this, no action is needed and your password stays the same.');
    }
}
