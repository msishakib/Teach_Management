<?php

namespace App\Services\Media;

use App\Enums\MediaCategory;
use App\Enums\MediaType;
use App\Models\Media;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class MediaService
{
    /**
     * Normalise mime type aliases reported by different libmagic builds.
     *
     * @var array<string, string>
     */
    protected const MIME_ALIASES = [
        'image/jpg' => 'image/jpeg',
        'image/pjpeg' => 'image/jpeg',
        'application/x-pdf' => 'application/pdf',
        'application/acrobat' => 'application/pdf',
        'application/x-zip-compressed' => 'application/zip',
    ];

    /**
     * Map a detected mime type to a safe file extension for the stored file.
     *
     * @var array<string, string>
     */
    protected const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    public function __construct(
        protected AntivirusService $antivirus,
        protected MediaQuotaService $quota,
    ) {
    }

    /**
     * Validate and store an uploaded file on the model's media collection.
     */
    public function store(
        UploadedFile $file,
        Model $model,
        MediaCategory $category,
        Authenticatable|int|null $uploader = null,
    ): Media {
        $this->validate($file, $category);
        $this->quota->assertCanStore($category, (int) $file->getSize());

        $originalName = $this->originalName($file);
        $imageSize = $category->type() === MediaType::Image ? @getimagesize($file->getRealPath()) : false;
        $preparedPath = $category->type() === MediaType::Image ? $this->convertToWebp($file) : null;
        $upload = $preparedPath === null
            ? $file
            : new UploadedFile($preparedPath, $originalName, 'image/webp', UPLOAD_ERR_OK, true);
        $extension = $this->extensionFor($upload, $category);
        $uploadedBy = $uploader instanceof Authenticatable ? $uploader->getAuthIdentifier() : ($uploader ?? Auth::id());

        try {
            /** @var Media $media */
            $media = $model
                ->addMedia($upload)
                ->usingFileName(Str::uuid()->toString().'.'.$extension)
                ->usingName($originalName)
                ->withCustomProperties(array_filter([
                    'original_name' => $originalName,
                    'uploaded_by' => $uploadedBy,
                    'image_width' => is_array($imageSize) ? (int) $imageSize[0] : null,
                    'image_height' => is_array($imageSize) ? (int) $imageSize[1] : null,
                ], fn ($value): bool => $value !== null))
                ->toMediaCollection($category->value, $category->disk());
        } finally {
            if ($preparedPath !== null && is_file($preparedPath)) {
                unlink($preparedPath);
            }
        }

        $media->forceFill([
            'original_name' => $originalName,
            'uploaded_by' => $uploadedBy,
        ])->save();

        return $media;
    }

    /**
     * Validate an upload without storing it (used by form requests).
     */
    public function validate(UploadedFile $file, MediaCategory $category): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => 'The upload did not complete. Please try again.',
            ]);
        }

        if ($file->getSize() > $category->maxSizeBytes()) {
            throw ValidationException::withMessages([
                'file' => 'The file may not be larger than '.round($category->maxSizeBytes() / 1024).' KB.',
            ]);
        }

        $this->assertExtensionAllowed($file);
        $this->assertMimeAllowed($file, $category);
        $this->antivirus->scan((string) $file->getRealPath());
    }

    public function delete(Media $media): void
    {
        // The medialibrary observer removes the files (and conversions) for us.
        $media->delete();
    }

    /**
     * A short-lived signed URL for a private media item.
     */
    public function temporaryUrl(Media $media, ?DateTimeInterface $expiration = null): string
    {
        $minutes = (int) config('media.signed_url_ttl', 5);

        return URL::temporarySignedRoute(
            'media.download',
            $expiration ?? now()->addMinutes($minutes),
            ['media' => $media->uuid],
        );
    }

    /**
     * A short-lived signed route for a lesson video. Authorization is checked
     * both here and again when the stream is opened.
     */
    public function temporaryVideoUrl(Media $media, ?\App\Models\User $user, ?DateTimeInterface $expiration = null): string
    {
        $media->loadMissing('model');
        $lesson = $media->model;

        abort_unless(
            $media->category() === MediaCategory::CourseVideo
                && $lesson instanceof \App\Models\Lesson
                && app(LessonAccessService::class)->canView($user, $lesson),
            403,
        );

        return URL::temporarySignedRoute(
            'media.video',
            $expiration ?? now()->addMinutes((int) config('media.signed_url_ttl', 5)),
            ['media' => $media->uuid],
        );
    }

    /**
     * Convert uploaded images to metadata-free WebP before retaining the
     * original; Media Library then generates responsive derivatives on queue.
     */
    protected function convertToWebp(UploadedFile $file): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('GD with WebP support is required to process uploaded images.');
        }

        $bytes = file_get_contents((string) $file->getRealPath());
        $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;

        if ($image === false) {
            throw ValidationException::withMessages([
                'file' => 'The image could not be decoded.',
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'media-webp-');

        if (! is_string($path)) {
            imagedestroy($image);
            throw new RuntimeException('A temporary file could not be created for image processing.');
        }

        $success = @imagewebp($image, $path, (int) config('media.conversion_quality', 82));
        imagedestroy($image);

        if (! $success) {
            @unlink($path);
            throw new RuntimeException('The image could not be converted to WebP.');
        }

        return $path;
    }

    protected function assertExtensionAllowed(UploadedFile $file): void
    {
        $name = strtolower($file->getClientOriginalName());
        $segments = explode('.', $name);
        $extensions = array_slice($segments, 1);
        $blocked = config('media.blocked_extensions', []);

        foreach ($extensions as $extension) {
            if ($extension !== '' && in_array($extension, $blocked, true)) {
                throw ValidationException::withMessages([
                    'file' => 'This file type is not allowed for security reasons.',
                ]);
            }
        }
    }

    protected function assertMimeAllowed(UploadedFile $file, MediaCategory $category): void
    {
        $mime = $this->detectMime($file);
        $accepted = $category->acceptedMimeTypes();

        if (! in_array($mime, $accepted, true)) {
            throw ValidationException::withMessages([
                'file' => 'The file content does not match an allowed type ('.$mime.').',
            ]);
        }
    }

    /**
     * Detect the real mime type from the file content (never the extension).
     */
    public function detectMime(UploadedFile $file): string
    {
        $mime = $this->sniffMime($file);
        $mime = strtolower($mime);
        $mime = self::MIME_ALIASES[$mime] ?? $mime;

        // OOXML documents are ZIP containers. Confirm their internal package
        // structure instead of trusting a user-controlled .docx/.xlsx suffix.
        if (in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
            return $this->detectOfficeMime($file) ?? $mime;
        }

        return $mime;
    }

    /**
     * Read the real mime type straight from the file bytes with libmagic.
     * Falls back to the framework guesser when the file cannot be inspected.
     */
    protected function sniffMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (is_string($path) && is_file($path) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($detected) && $detected !== '') {
                    return $detected;
                }
            }
        }

        return $file->getMimeType() ?: 'application/octet-stream';
    }

    protected function detectOfficeMime(UploadedFile $file): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();

        if ($zip->open((string) $file->getRealPath()) !== true) {
            return null;
        }

        try {
            $types = $zip->getFromName('[Content_Types].xml');

            if (! is_string($types)) {
                return null;
            }

            if (
                str_contains($types, 'wordprocessingml.document.main+xml')
                && $zip->locateName('word/document.xml') !== false
            ) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }

            if (
                str_contains($types, 'spreadsheetml.sheet.main+xml')
                && $zip->locateName('xl/workbook.xml') !== false
            ) {
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    protected function extensionFor(UploadedFile $file, MediaCategory $category): string
    {
        $mime = $this->detectMime($file);

        return self::MIME_EXTENSIONS[$mime]
            ?? strtolower($file->getClientOriginalExtension())
            ?: 'bin';
    }

    protected function originalName(UploadedFile $file): string
    {
        return (string) Str::of($file->getClientOriginalName())->limit(180, '');
    }
}
