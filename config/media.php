<?php

use App\Enums\MediaType;

return [

    /*
    |--------------------------------------------------------------------------
    | Default disk per media type
    |--------------------------------------------------------------------------
    |
    | The active disk for every individual media category can be overridden
    | from Settings (media.disk_{category}). Documents are always forced onto
    | a private disk, regardless of the configured value.
    |
    */

    'disks' => [
        MediaType::Image->value => env('MEDIA_IMAGE_DISK', 'public'),
        MediaType::Document->value => env('MEDIA_DOCUMENT_DISK', 'private'),
        MediaType::Video->value => env('MEDIA_VIDEO_DISK', 'cloud'),
    ],

    /*
    | Disks that are allowed to hold private (never publicly reachable) files.
    | A category flagged as private is silently moved to the default document
    | disk when Settings points it somewhere public.
    */
    'private_disks' => ['private', 'local'],

    /*
    |--------------------------------------------------------------------------
    | Allowed mime types (validated against the detected, real mime type)
    |--------------------------------------------------------------------------
    */

    'mime_types' => [
        MediaType::Image->value => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        MediaType::Document->value => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
        ],
        MediaType::Video->value => [
            'video/mp4',
            'video/webm',
        ],
    ],

    /*
    | Extensions that are never accepted, even when the detected mime type
    | looks harmless. Guards against "shell.php.jpg" style payloads.
    */
    'blocked_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'pht', 'phpt', 'shtml', 'htaccess', 'htpasswd',
        'exe', 'com', 'bat', 'cmd', 'msi', 'scr', 'cpl', 'jar', 'vbs', 'vbe',
        'js', 'jse', 'wsf', 'wsh', 'ps1', 'psm1', 'sh', 'bash', 'py', 'rb',
        'pl', 'cgi', 'asp', 'aspx', 'jsp', 'jspx', 'dll', 'so', 'bin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum upload size (KB) per type — overridable from Settings
    |--------------------------------------------------------------------------
    */

    'max_size_kb' => [
        MediaType::Image->value => 5 * 1024,
        MediaType::Document->value => 10 * 1024,
        MediaType::Video->value => 500 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image conversions (queued). Derived images are stored as WebP.
    |--------------------------------------------------------------------------
    */

    'conversions' => [
        'thumb' => 150,
        'medium' => 600,
        'large' => 1200,
    ],

    'conversion_quality' => 82,

    /*
    |--------------------------------------------------------------------------
    | Temporary URL lifetime (minutes) for private documents and videos
    |--------------------------------------------------------------------------
    */

    'signed_url_ttl' => (int) env('MEDIA_SIGNED_URL_TTL', 5),

    /*
    |--------------------------------------------------------------------------
    | Antivirus (ClamAV) — disabled by default, toggled from Settings
    |--------------------------------------------------------------------------
    */

    'antivirus' => [
        'enabled' => (bool) env('MEDIA_ANTIVIRUS_ENABLED', false),
        'binary' => env('CLAMAV_PATH', '/usr/bin/clamscan'),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunked / resumable uploads (temporary chunks)
    |--------------------------------------------------------------------------
    */

    'chunk' => [
        'disk' => env('MEDIA_CHUNK_DISK', 'local'),
        'directory' => 'media-chunks',
        'ttl_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload throttling (per user)
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'max_attempts' => (int) env('MEDIA_UPLOAD_MAX_ATTEMPTS', 30),
        'chunk_max_attempts' => (int) env('MEDIA_CHUNK_MAX_ATTEMPTS', 1200),
        'decay_minutes' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Video embed providers (validated and converted to privacy-friendly URLs)
    |--------------------------------------------------------------------------
    */

    'embed' => [
        'youtube' => [
            'hosts' => [
                'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
                'youtu.be', 'www.youtu.be',
                'youtube-nocookie.com', 'www.youtube-nocookie.com',
            ],
            'privacy_host' => 'www.youtube-nocookie.com',
        ],
        'vimeo' => [
            'hosts' => ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'],
            'privacy_host' => 'player.vimeo.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage quota (MB) per category — 0 means unlimited.
    | Individual categories can be overridden from Settings
    | (media.quota_{category}).
    |--------------------------------------------------------------------------
    */

    'quota_mb' => [
        'student_documents' => 0,
        'teacher_documents' => 0,
        'course_videos' => 0,
        'lesson_resources' => 0,
        'gallery' => 0,
        'notice_attachments' => 0,
        'assignment_submissions' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Models that may receive uploads through the HTTP upload endpoints.
    | Keys are the public aliases accepted from the client.
    |--------------------------------------------------------------------------
    */

    'upload_models' => [
        'student' => \App\Models\Student::class,
        'teacher' => \App\Models\Teacher::class,
        'course' => \App\Models\Course::class,
        'notice' => \App\Models\Notice::class,
        'gallery_image' => \App\Models\GalleryImage::class,
        'banner' => \App\Models\Banner::class,
        'lesson' => \App\Models\Lesson::class,
        'lesson_resource' => \App\Models\LessonResource::class,
        'assignment_submission' => \App\Models\AssignmentSubmission::class,
    ],
];
