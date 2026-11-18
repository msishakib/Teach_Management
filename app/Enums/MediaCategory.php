<?php

namespace App\Enums;

enum MediaCategory: string
{
    case StudentPhoto = 'student_photo';
    case StudentDocument = 'student_documents';
    case TeacherPhoto = 'teacher_photo';
    case TeacherDocument = 'teacher_documents';
    case CourseThumbnail = 'course_thumbnail';
    case CourseVideo = 'course_videos';
    case LessonResource = 'lesson_resources';
    case NoticeAttachment = 'notice_attachments';
    case AssignmentSubmission = 'assignment_submissions';
    case Gallery = 'gallery';
    case HomepageBanner = 'homepage_banner';

    public function type(): MediaType
    {
        return match ($this) {
            self::StudentPhoto,
            self::TeacherPhoto,
            self::CourseThumbnail,
            self::Gallery,
            self::HomepageBanner => MediaType::Image,

            self::StudentDocument,
            self::TeacherDocument,
            self::LessonResource,
            self::NoticeAttachment,
            self::AssignmentSubmission => MediaType::Document,

            self::CourseVideo => MediaType::Video,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::StudentPhoto => 'Student photos',
            self::StudentDocument => 'Student documents',
            self::TeacherPhoto => 'Teacher photos',
            self::TeacherDocument => 'Teacher documents',
            self::CourseThumbnail => 'Course thumbnails',
            self::CourseVideo => 'Course videos',
            self::LessonResource => 'Lesson resources',
            self::NoticeAttachment => 'Notice attachments',
            self::AssignmentSubmission => 'Assignment submissions',
            self::Gallery => 'Gallery images',
            self::HomepageBanner => 'Homepage banners',
        };
    }

    /**
     * Documents are never publicly reachable.
     */
    public function isPrivate(): bool
    {
        return $this->type() === MediaType::Document;
    }

    /**
     * Whether files must be accessed through an authorization-checked route.
     */
    public function isAccessRestricted(): bool
    {
        return $this->isPrivate() || $this === self::CourseVideo;
    }

    /**
     * Whether a model may hold more than one file in this collection.
     */
    public function isMultiple(): bool
    {
        return match ($this) {
            self::StudentDocument,
            self::TeacherDocument,
            self::CourseVideo,
            self::LessonResource,
            self::NoticeAttachment,
            self::AssignmentSubmission,
            self::Gallery => true,
            default => false,
        };
    }

    /**
     * Accepted mime types, validated against the real (detected) mime type.
     *
     * @return list<string>
     */
    public function acceptedMimeTypes(): array
    {
        return config("media.mime_types.{$this->type()->value}", []);
    }

    /**
     * Default disk for this category before Settings overrides are applied.
     */
    public function defaultDisk(): string
    {
        return (string) config("media.disks.{$this->type()->value}", 'public');
    }

    public function diskSettingKey(): string
    {
        return "media.disk_{$this->value}";
    }

    /**
     * The active disk for this category, honouring the Settings override.
     * Private categories can never resolve to a public disk.
     */
    public function disk(): string
    {
        $disk = setting($this->diskSettingKey()) ?: $this->defaultDisk();

        if ($this->type() === MediaType::Document && ! in_array($disk, config('media.private_disks', []), true)) {
            return (string) config('media.disks.document', 'private');
        }

        return (string) $disk;
    }

    /**
     * Max upload size in kilobytes, honouring the Settings override.
     */
    public function maxSizeKb(): int
    {
        $key = match ($this->type()) {
            MediaType::Image => 'media.image_max_size_kb',
            MediaType::Document => 'media.document_max_size_kb',
            MediaType::Video => 'media.video_max_size_kb',
        };

        return (int) (setting($key) ?: config("media.max_size_kb.{$this->type()->value}", 5120));
    }

    public function maxSizeBytes(): int
    {
        return $this->maxSizeKb() * 1024;
    }

    /**
     * Storage quota in bytes for this category. 0 = unlimited.
     */
    public function quotaBytes(): int
    {
        $mb = (int) (setting("media.quota_{$this->value}") ?? config("media.quota_mb.{$this->value}", 0));

        return max(0, $mb) * 1024 * 1024;
    }

    /**
     * @return array<string, int> conversion name => width in px
     */
    public function conversions(): array
    {
        if ($this->type() !== MediaType::Image) {
            return [];
        }

        return config('media.conversions', []);
    }

    /**
     * The private-disk download route requires the media.view_private permission.
     */
    public function requiresPrivatePermission(): bool
    {
        return $this->isPrivate();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }

    /**
     * @return array<string, string> value => label
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category): array => [$category->value => $category->label()])
            ->all();
    }

    public static function fromCollection(string $collection): ?self
    {
        return self::tryFrom($collection);
    }
}
