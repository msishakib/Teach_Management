<?php

namespace App\Services;

use App\Enums\NoticeAudience;
use App\Jobs\SendNotificationBatch;
use App\Models\Guardian;
use App\Models\Notice;
use App\Models\User;

class NotificationService
{
    public function __construct(protected SettingsService $settings) {}

    /**
     * Send a notice to intended recipients using enabled channels.
     */
    public function sendNotice(Notice $notice): void
    {
        $channels = array_keys(array_filter(config('notifications.channels', [])));

        $recipients = $this->recipientsFor($notice);

        $batchSize = max(1, (int) config('notifications.batch_size', 100));

        foreach ($channels as $channel) {
            foreach (array_chunk($recipients, $batchSize) as $chunk) {
                SendNotificationBatch::dispatch($chunk, $channel, $notice->title."\n\n".$notice->body, [
                    'notice_id' => $notice->id,
                ])->onQueue('notifications');
            }
        }
    }

    protected function recipientsFor(Notice $notice): array
    {
        $audience = $notice->audience instanceof NoticeAudience
            ? $notice->audience->value
            : (string) $notice->audience;

        return match ($audience) {
            NoticeAudience::Students->value => User::query()->whereHas('student')->get()->all(),
            NoticeAudience::Teachers->value => User::query()->whereHas('teacher')->get()->all(),
            NoticeAudience::Guardians->value => Guardian::query()->get()->all(),
            NoticeAudience::Batch->value => User::query()
                ->whereHas('student.enrollments', fn ($q) => $q->where('batch_id', $notice->batch_id))
                ->get()
                ->all(),
            default => User::query()->get()->all(),
        };
    }
}
