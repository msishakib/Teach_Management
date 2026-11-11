<?php

namespace App\Jobs;

use App\Notifications\Channels\BdSmsChannel;
use App\Notifications\Channels\DatabaseChannel;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\NotificationChannelInterface;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsLogChannel;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(protected array $recipients, protected string $channel, protected string $message, protected array $data = [])
    {
    }

    public function handle(): void
    {
        $driver = $this->driverFor($this->channel);

        foreach ($this->recipients as $recipient) {
            try {
                $driver->send($recipient, $this->message, $this->data);
            } catch (\Throwable $e) {
                // Swallow; driver should log failures.
            }
        }
    }

    protected function driverFor(string $channel): NotificationChannelInterface
    {
        return match ($channel) {
            'sms' => config('notifications.sms.bd_gateway.api_key') ? new BdSmsChannel() : new SmsLogChannel(),
            'email' => new EmailChannel(),
            'push' => new PushChannel(),
            default => new DatabaseChannel(),
        };
    }
}

