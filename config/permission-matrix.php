<?php

/**
 * Permission catalog: {module}.{action}
 *
 * Actions: view, create, update, delete, export, approve
 */
return [
    'guard' => 'web',

    'actions' => [
        'view',
        'create',
        'update',
        'delete',
        'export',
        'approve',
    ],

    'modules' => [
        'role',
        'user',
        'teacher',
        'student',
        'guardian',
        'academic_session',
        'course',
        'subject',
        'batch',
        'class_routine',
        'enrollment',
        'attendance',
        'leave_request',
        'exam',
        'quiz',
        'assignment',
        'fee',
        'payment',
        'voucher',
        'journal',
        'account',
        'report',
        'website',
    ],

    /*
     * Extra permissions that are not the full {module}.{action} grid.
     * Always seeded alongside module permissions.
     */
    'extra' => [
        'settings.update',
        'attendance.override',
        'exam.publish',
        'exam.override',
        'activity_log.view',
        'health.view',
        'media.view',
        'media.upload',
        'media.delete',
        'media.view_private',
        'payment.refund',
    ],

    /*
     * Role => module => actions (['*'] = all actions for that module).
     * super_admin bypasses checks via Gate::before and needs no explicit grants.
     */
    'matrix' => [
        'super_admin' => [],

        'admin' => [
            '*' => ['*'],
        ],

        'accountant' => [
            'fee' => ['*'],
            'payment' => ['*'],
            'voucher' => ['*'],
            'journal' => ['*'],
            'account' => ['*'],
            'report' => ['view', 'export'],
            'student' => ['view', 'export'],
            'enrollment' => ['view'],
            'payment.refund' => [],
        ],

        'teacher' => [
            'student' => ['view'],
            'academic_session' => ['view'],
            'course' => ['view'],
            'subject' => ['view'],
            'batch' => ['view'],
            'class_routine' => ['view'],
            'enrollment' => ['view'],
            'attendance' => ['view', 'create', 'update'],
            'leave_request' => ['view', 'create'],
            'exam' => ['view', 'create', 'update'],
            'quiz' => ['view', 'create', 'update', 'delete'],
            'assignment' => ['view', 'create', 'update', 'delete', 'approve'],
            'report' => ['view'],
            'media.view' => [],
            'media.upload' => [],
            'media.view_private' => [],
        ],

        'student' => [
            'course' => ['view'],
            'enrollment' => ['view'],
            'quiz' => ['view'],
            'assignment' => ['view', 'create', 'update'],
            'fee' => ['view'],
            'payment' => ['view'],
            'media.view' => [],
            'media.upload' => [],
        ],

        'guardian' => [
            'student' => ['view'],
            'course' => ['view'],
            'enrollment' => ['view'],
            'fee' => ['view'],
            'payment' => ['view'],
            'report' => ['view'],
            'media.view' => [],
        ],
    ],
];
