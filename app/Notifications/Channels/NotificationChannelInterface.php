<?php

namespace App\Notifications\Channels;

interface NotificationChannelInterface
{
    /**
     * Send a message to a single recipient/notifiable.
     *
     * @param  mixed  $notifiable
     * @param  string  $message
     * @param  array  $data
     * @return array{status: string, error?: string}
     */
    public function send($notifiable, string $message, array $data = []): array;
}

