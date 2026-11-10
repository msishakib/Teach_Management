<?php

use App\Services\SettingsService;

if (! function_exists('setting')) {
    /**
     * Read a cached application setting by "group.key".
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}
