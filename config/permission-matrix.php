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
        'fee',
        'payment',
        'voucher',
        'journal',
        'account',
        'report',
    ],

    /*
     * Extra permissions that are not the full {module}.{action} grid.
     * Always seeded alongside module permissions.
     */
    'extra' => [
        'settings.update',
        'attendance.override',
        'exam.publish',
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
            'report' => ['view'],
        ],

        'student' => [
            'course' => ['view'],
            'enrollment' => ['view'],
            'fee' => ['view'],
            'payment' => ['view'],
        ],

        'guardian' => [
            'student' => ['view'],
            'course' => ['view'],
            'enrollment' => ['view'],
            'fee' => ['view'],
            'payment' => ['view'],
            'report' => ['view'],
        ],
    ],
];
