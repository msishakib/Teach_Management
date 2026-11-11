<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements NotificationChannelInterface
{
    public function send($notifiable, string $message, array $data = []): array
    {
        try {
            Mail::raw($message, function ($m) use ($notifiable, $data) {
                $m->to($notifiable->email ?? $data['email'] ?? null);
                $m->subject($data['subject'] ?? 'Notification');
            });

            NotificationLog::create([
                'channel' => 'email',
                'recipient' => $notifiable->email ?? $data['email'] ?? null,
                'message' => $message,
                'status' => 'sent',
                'sent_at' => now(),
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : null,
                'notifiable_id' => is_object($notifiable) ? ($notifiable->id ?? null) : null,
            ]);

            return ['status' => 'sent'];
        } catch (\Throwable $e) {
            NotificationLog::create([
                'channel' => 'email',
                'recipient' => $notifiable->email ?? $data['email'] ?? null,
                'message' => $message,
                'status' => 'failed',
                'error' => (string) $e->getMessage(),
                'sent_at' => now(),
                'notifiable_type' => is_object($notifiable) ? get_class($notifiable) : null,
                'notifiable_id' => is_object($notifiable) ? ($notifiable->id ?? null) : null,
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}

