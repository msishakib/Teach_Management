<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use GuzzleHttp\Client;

class BdSmsChannel implements NotificationChannelInterface
{
    public function send($notifiable, string $message, array $data = []): array
    {
        $cfg = config('notifications.sms.bd_gateway');

        if (empty($cfg['endpoint']) || empty($cfg['api_key'])) {
            return ['status' => 'failed', 'error' => 'SMS gateway not configured'];
        }

        try {
            $client = new Client();
            $resp = $client->post($cfg['endpoint'], [
                'json' => [
                    'api_key' => $cfg['api_key'],
                    'to' => $data['phone'] ?? ($notifiable->phone ?? null),
                    'message' => $message,
                ],
                'timeout' => 5,
            ]);

            $body = (string) $resp->getBody();

            NotificationLog::create([
                'channel' => 'sms',
                'recipient' => $data['phone'] ?? ($notifiable->phone ?? null),
                'message' => $message,
                'status' => 'sent',
                'sent_at' => now(),
                'error' => null,
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : null,
                'notifiable_id' => is_object($notifiable) ? ($notifiable->id ?? null) : null,
            ]);

            return ['status' => 'sent'];
        } catch (\Throwable $e) {
            NotificationLog::create([
                'channel' => 'sms',
                'recipient' => $data['phone'] ?? ($notifiable->phone ?? null),
                'message' => $message,
                'status' => 'failed',
                'sent_at' => now(),
                'error' => (string) $e->getMessage(),
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : null,
                'notifiable_id' => is_object($notifiable) ? ($notifiable->id ?? null) : null,
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}

