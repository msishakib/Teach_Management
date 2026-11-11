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
            'city' => null,
            'country' => 'BD',
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
            'fail_if_any_subject_fails' => true,
            'gpa_zero_on_fail' => true,
            'notify_on_result_publish' => false,
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

        /*
         * Public website (managed from Website → Website settings).
         * stat_* values override the live counters when filled in.
         */
        'website' => [
            'tagline' => 'Learn smarter. Score higher. Grow further.',
            'tagline_bn' => 'স্মার্টভাবে শিখুন, সেরা ফল করুন, এগিয়ে যান।',
            'meta_description' => null,
            'meta_description_bn' => null,
            'primary_color' => '#4f46e5',
            'accent_color' => '#c026d3',
            'facebook' => null,
            'youtube' => null,
            'instagram' => null,
            'linkedin' => null,
            'twitter' => null,
            'whatsapp' => null,
            'map_embed_url' => null,
            'office_hours' => 'Sat – Thu, 9:00 AM – 8:00 PM',
            'stat_students' => null,
            'stat_teachers' => null,
            'stat_courses' => null,
            'stat_success_rate' => null,
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
            'certificate_prefix' => 'CERT',
            'admission_yearly_sequences' => [],
            'employee_yearly_sequences' => [],
            'invoice_yearly_sequences' => [],
            'fee_receipt_yearly_sequences' => [],
            'payment_voucher_yearly_sequences' => [],
            'receive_voucher_yearly_sequences' => [],
            'certificate_yearly_sequences' => [],
            'format' => '{PREFIX}-{YEAR}-{NUMBER}',
            'padding' => 4,
        ],
    ],
];
