<?php

namespace App\Providers;

use App\Events\NoticePublished;
use App\Listeners\SendNoticeNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NoticePublished::class => [
            SendNoticeNotifications::class,
        ],
    ];
}

