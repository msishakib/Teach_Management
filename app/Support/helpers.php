<?php

use App\Http\Middleware\SetPublicLocale;
use App\Services\SettingsService;

if (! function_exists('public_home_url')) {
    /**
     * Public homepage URL with an explicit locale (safe outside {locale} routes).
     */
    function public_home_url(?string $locale = null): string
    {
        $locale ??= in_array(app()->getLocale(), SetPublicLocale::SUPPORTED, true)
            ? app()->getLocale()
            : SetPublicLocale::preferred(request());

        return route('home', ['locale' => $locale]);
    }
}

if (! function_exists('setting')) {
    /**
     * Read a cached application setting by "group.key".
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('public_image')) {
    /**
     * URL for a file stored on the "public" disk (relative, so it works on any host/port).
     */
    function public_image(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}

if (! function_exists('site_number')) {
    /**
     * Locale-aware number (Bangla digits when the public site is in Bangla).
     */
    function site_number(int|float|string|null $value, int $decimals = 0, bool $grouping = true): string
    {
        $formatter = new NumberFormatter(app()->getLocale() === 'bn' ? 'bn_BD' : 'en_US', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::GROUPING_USED, $grouping ? 1 : 0);

        return (string) $formatter->format((float) $value);
    }
}

if (! function_exists('course_duration')) {
    /**
     * Human-friendly duration from a number of seconds (e.g. "2h 15m").
     */
    function course_duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0 && $minutes > 0) {
            return site_number($hours).'h '.site_number($minutes).'m';
        }

        if ($hours > 0) {
            return site_number($hours).'h';
        }

        return site_number(max(1, $minutes)).'m';
    }
}

if (! function_exists('site_date')) {
    /**
     * Locale-aware date in the institute timezone, e.g. "11 Nov 2026" / "১১ নভেম্বর ২০২৬".
     */
    function site_date(?DateTimeInterface $date, string $pattern = 'd MMM y'): string
    {
        if ($date === null) {
            return '';
        }

        // Resolved once per application instance (request) instead of once per call.
        if (! app()->bound('site.timezone')) {
            app()->instance('site.timezone', (string) setting('general.timezone', 'Asia/Dhaka'));
        }

        $formatter = new IntlDateFormatter(
            app()->getLocale() === 'bn' ? 'bn_BD' : 'en_US',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            app('site.timezone'),
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        return (string) $formatter->format($date);
    }
}
