<?php

return [
    'cache_key' => 'app.settings',

    /*
     * Default settings keyed as group => [key => value].
     * Seeded by SettingsSeeder; existing rows are left untouched.
     */
    'defaults' => [
        'general' => [
            'institute_name' => 'Teach Management Institute',
            'logo' => null,
            'address' => null,
            'phone' => null,
            'email' => null,
            'currency_symbol' => '৳',
            'date_format' => 'd M Y',
            'timezone' => 'Asia/Dhaka',
        ],

        'academic' => [
            'current_session' => null,
            'month_names' => [
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December',
            ],
        ],

        'finance' => [
            'fiscal_year_start_month' => 7,
            'receipt_prefix' => 'RCPT',
            'payment_prefix' => 'PAY',
            'journal_prefix' => 'JV',
            'opening_prefix' => 'OPN',
            'invoice_prefix' => 'INV',
            'voucher_number_format' => '{PREFIX}-{YEAR}-{NUMBER}',
            'entry_yearly_sequences' => [],
            'invoice_due_days' => 10,
            'late_fee_rule' => [
                'type' => 'percentage',
                'value' => 0,
                'grace_days' => 0,
            ],
            'default_cash_account' => null,
            'default_bank_account' => null,
            'allow_negative_cash' => false,
        ],

        'notification' => [
            'sms_enabled' => false,
            'email_enabled' => true,
        ],

        'attendance' => [
            'min_percent' => 75,
            'notify_guardian_on_absent' => false,
        ],

        'numbering' => [
            'admission_prefix' => 'ADM',
            'employee_prefix' => 'EMP',
            'invoice_prefix' => 'INV',
            'fee_receipt_prefix' => 'RCPT',
            'payment_voucher_prefix' => 'PAY',
            'receive_voucher_prefix' => 'RV',
            'admission_yearly_sequences' => [],
            'employee_yearly_sequences' => [],
            'invoice_yearly_sequences' => [],
            'fee_receipt_yearly_sequences' => [],
            'payment_voucher_yearly_sequences' => [],
            'receive_voucher_yearly_sequences' => [],
            'format' => '{PREFIX}-{YEAR}-{NUMBER}',
            'padding' => 4,
        ],
    ],
];
