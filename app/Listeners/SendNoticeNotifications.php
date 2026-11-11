<?php

namespace App\Listeners;

use App\Events\NoticePublished;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendNoticeNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected NotificationService $notifications) {}

    public function handle(NoticePublished $event): void
    {
        $this->notifications->sendNotice($event->notice);
    }
}

