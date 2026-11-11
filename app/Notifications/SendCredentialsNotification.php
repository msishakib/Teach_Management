<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $password) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your account credentials')
            ->line('An account has been created for you.')
            ->line('Email: '.$notifiable->email)
            ->line('Password: '.$this->password)
            ->line('Please change your password after first login.');
    }
}

