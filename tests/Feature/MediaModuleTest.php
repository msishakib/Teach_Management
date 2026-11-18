<?php

namespace Tests\Feature;

use App\Enums\LessonVideoType;
use App\Enums\MediaCategory;
use App\Enums\RoleName;
use App\Filament\Resources\MediaResource\Widgets\MediaUploadWidget;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Student;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Media\ChunkedUploadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MediaModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['queue.default' => 'sync']);
    }

    public function test_image_upload_is_converted_to_webp_and_stored_with_a_random_name(): void
    {
        Storage::fake('public');
        $student = Student::factory()->create();

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('portrait.png', 80, 60),
            $student,
            MediaCategory::StudentPhoto,
        );

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.webp$/', $media->file_name);
        $this->assertSame(80, $media->getCustomProperty('image_width'));
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());

        $html = Blade::render('<x-media-image :media="$media" />', ['media' => $media]);

        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('width="80"', $html);
        $this->assertStringContainsString('height="60"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);

        $media->delete();
        Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
    }

    public function test_wrong_mime_with_a_fake_extension_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(MediaService::class)->validate(
            UploadedFile::fake()->createWithContent('not-an-image.jpg', 'plain text'),
            MediaCategory::StudentPhoto,
        );
    }

    public function test_uploads_over_the_configured_size_limit_are_rejected(): void
    {
        config(['media.max_size_kb.document' => 1]);
        $this->expectException(ValidationException::class);

        app(MediaService::class)->validate(
            UploadedFile::fake()->createWithContent('oversize.pdf', str_repeat('x', 1025)),
            MediaCategory::StudentDocument,
        );
    }

    public function test_private_documents_require_permission_and_signed_urls_expire(): void
    {
        Storage::fake('private');
        $student = Student::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'record.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
        );
        $media = app(MediaService::class)->store($file, $student, MediaCategory::StudentDocument);
        $url = app(MediaService::class)->temporaryUrl($media);

        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertForbidden();

        $authorized = User::factory()->create();
        Permission::findOrCreate('media.view_private', 'web');
        $authorized->givePermissionTo('media.view_private');

        $this->actingAs($authorized)
            ->get($url)
            ->assertOk();

        $this->travel(6)->minutes();

        $this->actingAs($authorized)
            ->get($url)
            ->assertForbidden();
    }

    public function test_unenrolled_student_cannot_open_an_uploaded_lesson_video(): void
    {
        Storage::fake('cloud');
        $studentUser = User::factory()->student()->create();
        Student::factory()->create(['user_id' => $studentUser->id]);
        $lesson = Lesson::query()->create([
            'course_id' => Course::factory()->create()->id,
            'title' => 'Restricted lesson',
            'video_type' => LessonVideoType::Upload,
            'video_url_or_media_id' => null,
        ]);
        $media = Media::query()->forceCreate([
            'model_type' => $lesson->getMorphClass(),
            'model_id' => $lesson->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => MediaCategory::CourseVideo->value,
            'name' => 'lesson',
            'original_name' => 'lesson.mp4',
            'file_name' => (string) Str::uuid().'.mp4',
            'mime_type' => 'video/mp4',
            'disk' => 'cloud',
            'conversions_disk' => 'cloud',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
        $lesson->update(['video_url_or_media_id' => (string) $media->id]);

        $this->actingAs($studentUser)
            ->get(route('lessons.video', $lesson))
            ->assertForbidden();

        $signedVideoUrl = URL::temporarySignedRoute(
            'media.video',
            now()->addMinutes(5),
            ['media' => $media->uuid],
        );

        $this->actingAs($studentUser)
            ->get($signedVideoUrl)
            ->assertForbidden();
    }

    public function test_lesson_embed_links_are_validated_and_rewritten_to_privacy_friendly_urls(): void
    {
        $lesson = Lesson::query()->create([
            'course_id' => Course::factory()->create()->id,
            'title' => 'Embedded lesson',
            'video_type' => LessonVideoType::Embed,
            'video_url_or_media_id' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            $lesson->video_url_or_media_id,
        );
    }

    public function test_lesson_rejects_non_youtube_and_vimeo_embed_links(): void
    {
        $this->expectException(ValidationException::class);

        Lesson::query()->create([
            'course_id' => Course::factory()->create()->id,
            'title' => 'Invalid embed lesson',
            'video_type' => LessonVideoType::Embed,
            'video_url_or_media_id' => 'https://example.com/video',
        ]);
    }

    public function test_enrolled_student_can_open_a_privacy_friendly_embed_lesson(): void
    {
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['course_id' => $course->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
        ]);
        $lesson = Lesson::query()->create([
            'course_id' => $course->id,
            'title' => 'Enrolled lesson',
            'video_type' => LessonVideoType::Embed,
            'video_url_or_media_id' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $this->actingAs($user)
            ->get(route('lessons.video', $lesson))
            ->assertOk()
            ->assertJsonPath('url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
    }

    public function test_chunked_upload_sessions_are_private_and_reject_size_overruns(): void
    {
        Storage::fake('local');
        $uploads = app(ChunkedUploadService::class);
        $session = $uploads->initiate(MediaCategory::CourseVideo, 'lesson.mp4', 5, 10);

        try {
            $uploads->status($session['upload_id'], 11);
            $this->fail('A different uploader must not inspect another upload session.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->expectException(ValidationException::class);
        $uploads->appendChunk(
            $session['upload_id'],
            0,
            UploadedFile::fake()->createWithContent('part', '123456'),
            10,
        );
    }

    public function test_media_library_shows_the_upload_widget_to_uploaders(): void
    {
        $user = User::factory()->admin()->create();
        $user->assignRole(RoleName::Admin->value);
        $user->givePermissionTo('media.upload');
        $this->assertTrue($user->can('media.upload'));
        $this->actingAs($user);
        $this->assertTrue(MediaUploadWidget::canView());

        $this->get('/admin/media')
            ->assertOk()
            ->assertSee('Upload media');
    }
}
