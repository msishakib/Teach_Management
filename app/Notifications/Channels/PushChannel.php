<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;

class PushChannel implements NotificationChannelInterface
{
    public function send($notifiable, string $message, array $data = []): array
    {
        // Placeholder for FCM push implementation. For now log as sent.
        NotificationLog::create([
            'channel' => 'push',
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

