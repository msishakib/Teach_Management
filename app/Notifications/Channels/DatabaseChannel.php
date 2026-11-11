<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;

class DatabaseChannel implements NotificationChannelInterface
{
    public function send($notifiable, string $message, array $data = []): array
    {
        NotificationLog::create([
            'channel' => 'database',
            'recipient' => $notifiable->id ?? null,
            'message' => $message,
            'status' => 'sent',
            'sent_at' => now(),
            'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : null,
            'notifiable_id' => is_object($notifiable) ? ($notifiable->id ?? null) : null,
        ]);

        return ['status' => 'sent'];
    }
}

