<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login rate limiting
    |--------------------------------------------------------------------------
    |
    | These values are used as a fallback. Admins can override the attempt
    | count from Settings > Security (security.login_max_attempts).
    |
    */
    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password policy
    |--------------------------------------------------------------------------
    |
    | Applies to every password change made through the application. Admins
    | can tighten these from Settings > Security.
    |
    */
    'password' => [
        'min_length' => (int) env('PASSWORD_MIN_LENGTH', 12),
        'mixed_case' => (bool) env('PASSWORD_MIXED_CASE', true),
        'numbers' => (bool) env('PASSWORD_NUMBERS', true),
        'symbols' => (bool) env('PASSWORD_SYMBOLS', true),
        'uncompromised' => (bool) env('PASSWORD_UNCOMPROMISED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    */
    'session_timeout_minutes' => (int) env('SESSION_TIMEOUT_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication
    |--------------------------------------------------------------------------
    |
    | Users can always opt in from the "Two-factor authentication" page.
    | When enforce_for_admins is true, admins without 2FA are redirected to
    | the setup page after signing in.
    |
    */
    'two_factor' => [
        'enforce_for_admins' => (bool) env('TWO_FACTOR_ENFORCE_ADMINS', false),
        'window' => 1,
        'recovery_codes' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP security headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),

        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),

        'csp' => env(
            'SECURITY_CSP',
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' https:; frame-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
        ),

        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
            'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
            'include_sub_domains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Production hardening
    |--------------------------------------------------------------------------
    */
    'force_debug_off_in_production' => (bool) env('SECURITY_FORCE_DEBUG_OFF', true),

];
