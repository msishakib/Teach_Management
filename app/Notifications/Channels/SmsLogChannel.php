<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;

class SmsLogChannel implements NotificationChannelInterface
{
    public function send($notifiable, string $message, array $data = []): array
    {
        // Log SMS to notification_logs for local/dev
        NotificationLog::create([
            'channel' => 'sms',
            'recipient' => $data['phone'] ?? ($notifiable->phone ?? null),
            'message' => $message,
            'status' => 'sent',
            'sent_at' => now(),
            'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : null,
            'notifiable_id' => is_object($notifiable) ? ($notifiable->id ?? null) : null,
        ]);

        return ['status' => 'sent'];
    }
}

