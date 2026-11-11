<?php

return [
    'channels' => [
        'database' => true,
        'email' => env('NOTIFY_EMAIL', true),
        'sms' => env('NOTIFY_SMS', false),
        'push' => env('NOTIFY_PUSH', false),
    ],

    // BD SMS gateway config placeholder
    'sms' => [
        'bd_gateway' => [
            'endpoint' => env('BD_SMS_ENDPOINT', ''),
            'api_key' => env('BD_SMS_API_KEY', ''),
        ],
    ],

    'batch_size' => env('NOTIFY_BATCH_SIZE', 100),
];

