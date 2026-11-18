<?php

namespace App\Support\ActivityLog;

use Closure;
use Spatie\Activitylog\Contracts\LoggablePipe;
use Spatie\Activitylog\EventLogBag;

/**
 * Adds request metadata to every audit entry. The activity log package only
 * stores model changes by default, so we attach the IP and user agent here.
 */
class AddRequestContext implements LoggablePipe
{
    public function handle(EventLogBag $event, Closure $next): EventLogBag
    {
        $request = app()->bound('request') ? request() : null;

        $ip = $request?->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $request?->userAgent() ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);

        $event->changes['ip_address'] = $ip;
        $event->changes['user_agent'] = $userAgent;

        return $next($event);
    }
}
