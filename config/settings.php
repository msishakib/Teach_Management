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

        /*
         * Online course checkout. Secrets stay in .env; Settings can override
         * non-secret defaults (gateway choice, merchant display numbers).
         */
        'payments' => [
            'default_gateway' => null, // falls back to config('payments.default') / PAYMENT_GATEWAY
            'sslcommerz_sandbox' => true,
            'bkash_sandbox' => true,
            'manual_bkash_number' => null,
            'manual_nagad_number' => null,
            'manual_instructions' => 'Send the exact net amount, then submit the TrxID for verification.',
            'order_prefix' => 'ORD',
        ],

        'security' => [
            'login_max_attempts' => 5,
            'session_timeout_minutes' => 60,
            'password_min_length' => 12,
            'password_mixed_case' => true,
            'password_numbers' => true,
            'password_symbols' => true,
            'password_uncompromised' => false,
            'two_factor_admin_required' => false,
        ],

        'attendance' => [
            'min_percent' => 75,
            'notify_guardian_on_absent' => false,
        ],

        /*
         * Central media module. Disk selection is per category; documents are
         * always forced onto a private disk by the media service.
         */
        'media' => [
            'disk_student_photo' => 'public',
            'disk_student_documents' => 'private',
            'disk_teacher_photo' => 'public',
            'disk_teacher_documents' => 'private',
            'disk_course_thumbnail' => 'public',
            'disk_course_videos' => 'cloud',
            'disk_lesson_resources' => 'private',
            'disk_notice_attachments' => 'private',
            'disk_assignment_submissions' => 'private',
            'disk_gallery' => 'public',
            'disk_homepage_banner' => 'public',
            'image_max_size_kb' => 5120,
            'document_max_size_kb' => 10240,
            'video_max_size_kb' => 512000,
            'quota_student_documents' => 0,
            'quota_teacher_documents' => 0,
            'quota_course_videos' => 0,
            'quota_lesson_resources' => 0,
            'quota_gallery' => 0,
            'quota_notice_attachments' => 0,
            'quota_assignment_submissions' => 0,
            'antivirus_enabled' => false,
            'clamav_path' => '/usr/bin/clamscan',
            'upload_max_attempts' => 30,
        ],

        'numbering' => [
            'admission_prefix' => 'ADM',
            'employee_prefix' => 'EMP',
            'invoice_prefix' => 'INV',
            'fee_receipt_prefix' => 'RCPT',
            'payment_voucher_prefix' => 'PAY',
            'receive_voucher_prefix' => 'RV',
            'certificate_prefix' => 'CERT',
            'order_prefix' => 'ORD',
            'registration_prefix' => 'REG',
            'admission_yearly_sequences' => [],
            'employee_yearly_sequences' => [],
            'invoice_yearly_sequences' => [],
            'fee_receipt_yearly_sequences' => [],
            'payment_voucher_yearly_sequences' => [],
            'receive_voucher_yearly_sequences' => [],
            'certificate_yearly_sequences' => [],
            'order_yearly_sequences' => [],
            'registration_yearly_sequences' => [],
            'format' => '{PREFIX}-{YEAR}-{NUMBER}',
            'padding' => 4,
        ],
    ],
];
