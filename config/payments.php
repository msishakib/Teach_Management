<?php

return [
    /*
     * Default gateway driver. Overridable via Settings (payments.default_gateway).
     * Use "fake" locally; production should be sslcommerz, bkash, or manual.
     */
    'default' => env('PAYMENT_GATEWAY', 'fake'),

    'drivers' => [
        'sslcommerz' => [
            'store_id' => env('SSLCOMMERZ_STORE_ID'),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
            'sandbox' => (bool) env('SSLCOMMERZ_SANDBOX', true),
            'success_url' => null,
            'fail_url' => null,
            'cancel_url' => null,
            'ipn_url' => null,
        ],

        'bkash' => [
            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
            'sandbox' => (bool) env('BKASH_SANDBOX', true),
            'callback_url' => null,
        ],

        'manual' => [
            'bkash_number' => env('MANUAL_BKASH_NUMBER'),
            'nagad_number' => env('MANUAL_NAGAD_NUMBER'),
            'instructions' => env('MANUAL_PAYMENT_INSTRUCTIONS'),
        ],

        'fake' => [
            // Intentionally empty — local/test only.
        ],
    ],
];
