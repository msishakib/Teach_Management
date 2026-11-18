<?php

namespace Tests\Feature;

use App\Enums\DeliveryMode;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\LessonVideoType;
use App\Enums\MediaCategory;
use App\Enums\RoleName;
use App\Filament\Resources\CourseResource\Pages\EditCourse;
use App\Filament\Resources\CourseResource\RelationManagers\SectionsRelationManager;
use App\Filament\Resources\CourseSectionResource\Pages\EditCourseSection;
use App\Filament\Resources\CourseSectionResource\RelationManagers\LessonsRelationManager;
use App\Models\Batch;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Media;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\CourseAccessService;
use App\Services\Media\MediaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CourseLmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_free_preview_lesson_video_is_public_without_login(): void
    {
        $course = $this->publishedCourse();
        $lesson = $this->lesson($course, [
            'title' => 'Open preview',
            'is_free_preview' => true,
        ]);

        $this->get("/en/courses/{$course->code}")
            ->assertOk()
            ->assertSee('Open preview')
            ->assertSee(__('course.curriculum.preview'));

        $this->get(route('lessons.video', $lesson))
            ->assertOk()
            ->assertJsonPath('video_type', 'embed')
            ->assertJsonPath('url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
    }

    public function test_unpublished_lessons_are_hidden_and_cannot_be_opened(): void
    {
        $course = $this->publishedCourse();
        $this->lesson($course, ['title' => 'Visible lesson', 'is_free_preview' => true]);
        $hidden = $this->lesson($course, [
            'title' => 'Secret lesson',
            'is_published' => false,
            'is_free_preview' => true,
        ]);

        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $this->get("/en/courses/{$course->code}")
            ->assertOk()
            ->assertSee('Visible lesson')
            ->assertDontSee('Secret lesson');

        $this->get(route('lessons.video', $hidden))->assertForbidden();

        $this->actingAs($user)
            ->get("/en/courses/{$course->code}/learn/{$hidden->id}")
            ->assertNotFound();
    }

    public function test_enrolled_student_can_open_the_player_and_lesson_video(): void
    {
        $course = $this->publishedCourse();
        $lesson = $this->lesson($course, ['title' => 'Enrolled lesson']);
        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $this->actingAs($user)
            ->get("/en/courses/{$course->code}/learn/{$lesson->id}")
            ->assertOk()
            ->assertSee('Enrolled lesson')
            ->assertSee(__('course.player.content'));

        $this->actingAs($user)
            ->get(route('lessons.video', $lesson))
            ->assertOk()
            ->assertJsonPath('video_type', 'embed');
    }

    public function test_unenrolled_student_is_denied_the_course_and_lesson(): void
    {
        $course = $this->publishedCourse();
        $lesson = $this->lesson($course, ['title' => 'Locked lesson']);
        $user = $this->studentUser();

        $this->actingAs($user)
            ->get("/en/courses/{$course->code}/learn/{$lesson->id}")
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('lessons.video', $lesson))
            ->assertForbidden();
    }

    public function test_expired_enrollment_and_elapsed_access_days_revoke_access(): void
    {
        $course = $this->publishedCourse();
        $lesson = $this->lesson($course, ['title' => 'Expiring lesson']);
        $user = $this->studentUser();

        $this->enroll($course, $user->student, [
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('lessons.video', $lesson))
            ->assertForbidden();

        Enrollment::query()->withoutGlobalScopes()->update([
            'expires_at' => null,
            'enrolled_on' => now()->subDays(45)->toDateString(),
        ]);
        $course->update(['access_days' => 30]);

        $this->actingAs($user)
            ->get(route('lessons.video', $lesson))
            ->assertForbidden();

        $this->assertFalse(app(CourseAccessService::class)->canAccessCourse($user, $course->fresh()));
    }

    public function test_drip_locked_lessons_unlock_after_the_configured_days(): void
    {
        $course = $this->publishedCourse();
        $lesson = $this->lesson($course, [
            'title' => 'Dripped lesson',
            'drip_after_days' => 7,
        ]);
        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $this->actingAs($user)
            ->get(route('lessons.video', $lesson))
            ->assertForbidden();

        $this->actingAs($user)
            ->get("/en/courses/{$course->code}/learn/{$lesson->id}")
            ->assertForbidden();

        $this->travel(8)->days();

        $this->actingAs($user)
            ->get(route('lessons.video', $lesson))
            ->assertOk();
    }

    public function test_lesson_resource_download_follows_lesson_access(): void
    {
        Storage::fake('private');

        $course = $this->publishedCourse();
        $lesson = $this->lesson($course, ['title' => 'Resource lesson', 'is_free_preview' => true]);
        $resource = $this->resourceFor($lesson, 'Study guide');

        // Free-preview lessons expose their files to guests.
        $this->get(route('lessons.resources.download', ['lesson' => $lesson, 'resource' => $resource]))
            ->assertOk();

        // A non-preview lesson stays private until the learner is enrolled.
        $lesson->update(['is_free_preview' => false]);
        $guestUrl = route('lessons.resources.download', ['lesson' => $lesson, 'resource' => $resource]);
        $this->get($guestUrl)->assertForbidden();

        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $this->actingAs($user)
            ->get($guestUrl)
            ->assertOk();
    }

    public function test_online_enrollment_reuses_enrollments_and_sets_expiry(): void
    {
        $course = $this->publishedCourse([
            'access_days' => 15,
            'price' => null,
            'delivery_mode' => DeliveryMode::Online,
        ]);
        $batch = Batch::factory()->create(['course_id' => $course->id]);
        $user = $this->studentUser();

        $response = $this->actingAs($user)
            ->post("/en/courses/{$course->code}/enroll");

        $response->assertRedirect(route('courses.learn', [
            'locale' => 'en',
            'course' => $course->code,
        ]));

        $enrollment = Enrollment::query()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame($user->student->id, (int) $enrollment->student_id);
        $this->assertSame($batch->id, (int) $enrollment->batch_id);
        $this->assertSame(EnrollmentSource::Online, $enrollment->enrolled_via);
        $this->assertNotNull($enrollment->expires_at);
        $this->assertTrue($enrollment->expires_at->isFuture());
        $this->assertTrue(app(CourseAccessService::class)->canAccessCourse($user, $course));
    }

    public function test_offline_courses_use_the_application_form_instead_of_instant_enrollment(): void
    {
        $course = $this->publishedCourse(['delivery_mode' => DeliveryMode::Offline]);
        $user = $this->studentUser();

        $this->get("/en/courses/{$course->code}")
            ->assertOk()
            ->assertSee(route('apply', ['locale' => 'en', 'course' => $course->id]), false);

        $this->actingAs($user)
            ->post("/en/courses/{$course->code}/enroll")
            ->assertNotFound();

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_student_portal_links_enrolled_courses_to_the_online_player(): void
    {
        $course = $this->publishedCourse();
        $this->lesson($course, ['title' => 'First lesson', 'is_free_preview' => true]);
        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $this->actingAs($user)
            ->get('/student/my-courses')
            ->assertOk()
            ->assertSee('Open course')
            ->assertSee(route('courses.learn', ['locale' => 'en', 'course' => $course->code]), false);
    }

    public function test_admin_can_manage_sections_and_lessons_in_filament(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);

        $course = $this->publishedCourse();
        $this->lesson($course, ['title' => 'Editable lesson']);
        $section = CourseSection::query()->where('course_id', $course->id)->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(EditCourse::class, ['record' => $course->id])
            ->assertSuccessful()
            ->assertSeeLivewire(SectionsRelationManager::class);

        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])
            ->assertSuccessful()
            ->assertSee('Curriculum sections')
            ->assertSee('Getting started');

        Livewire::test(EditCourseSection::class, ['record' => $section->id])
            ->assertSuccessful()
            ->assertSeeLivewire(LessonsRelationManager::class);

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $section,
            'pageClass' => EditCourseSection::class,
        ])
            ->assertSuccessful()
            ->assertSee('Editable lesson');
    }

    protected function publishedCourse(array $attributes = []): Course
    {
        return Course::factory()->create(array_merge([
            'is_active' => true,
            'is_published' => true,
            'delivery_mode' => DeliveryMode::Online,
        ], $attributes));
    }

    protected function lesson(Course $course, array $attributes = []): Lesson
    {
        $section = CourseSection::query()->firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Getting started'],
            ['sort_order' => 1],
        );

        return Lesson::query()->create(array_merge([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => 'Lesson',
            'type' => LessonType::Video,
            'video_type' => LessonVideoType::Embed,
            'video_url_or_media_id' => 'https://youtu.be/dQw4w9WgXcQ',
            'duration_seconds' => 630,
            'is_published' => true,
        ], $attributes));
    }

    protected function studentUser(): User
    {
        $user = User::factory()->student()->create();
        Student::factory()->create(['user_id' => $user->id]);

        return $user->fresh(['student']);
    }

    protected function enroll(Course $course, Student $student, array $attributes = []): Enrollment
    {
        $batch = Batch::query()->firstOrCreate(['course_id' => $course->id], [
            'name' => 'Online',
            'session_id' => \App\Models\AcademicSession::factory()->create()->id,
            'start_date' => now()->toDateString(),
            'capacity' => 0,
            'status' => \App\Enums\BatchStatus::Open,
        ]);

        return Enrollment::query()->create(array_merge([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => \App\Enums\EnrollmentStatus::Active,
        ], $attributes));
    }

    protected function resourceFor(Lesson $lesson, string $title): LessonResource
    {
        $resource = LessonResource::query()->create([
            'lesson_id' => $lesson->id,
            'title' => $title,
        ]);

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->createWithContent(
                'guide.pdf',
                "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
            ),
            $resource,
            MediaCategory::LessonResource,
        );

        $resource->update(['media_id' => $media->id]);

        $this->assertInstanceOf(Media::class, $media);

        return $resource->fresh(['fileMedia']);
    }
}
