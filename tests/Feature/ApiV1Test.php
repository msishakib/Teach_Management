<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\DeliveryMode;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonType;
use App\Enums\LessonVideoType;
use App\Enums\PaymentGateway;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Enums\ShowAnswersAfter;
use App\Models\AcademicSession;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\ClassRoutine;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Notice;
use App\Models\Order;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentInvoice;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_returns_a_token_and_profile_requires_authentication(): void
    {
        $user = $this->studentUser();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']]);

        $this->getJson('/api/v1/profile')->assertUnauthorized();
    }

    public function test_profile_returns_the_authenticated_student(): void
    {
        $user = $this->studentUser();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.student.id', $user->student->id);
    }

    public function test_student_can_list_routine_attendance_invoices_receipts_and_notices(): void
    {
        [$user, $course, $batch] = $this->studentContext();
        Sanctum::actingAs($user);

        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->create();
        ClassRoutine::factory()->create([
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $session = AttendanceSession::factory()->create(['batch_id' => $batch->id]);
        StudentAttendance::factory()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $user->student->id,
        ]);

        $invoice = StudentInvoice::factory()->create([
            'student_id' => $user->student->id,
            'batch_id' => $batch->id,
        ]);

        Receipt::factory()->create(['student_id' => $user->student->id]);
        Notice::factory()->create(['title' => 'Visible notice']);

        $this->getJson('/api/v1/routine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.id', $subject->id);

        $this->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'present');

        $this->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoice->id);

        $this->getJson('/api/v1/receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/notices')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Visible notice');
    }

    public function test_student_can_list_enrolled_courses_and_lessons(): void
    {
        [$user, $course] = $this->studentContext();
        Sanctum::actingAs($user);

        $section = CourseSection::query()->create([
            'course_id' => $course->id,
            'title' => 'Section 1',
            'sort_order' => 1,
        ]);

        $lesson = $this->lesson($course, $section);

        $this->getJson('/api/v1/my-courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.lessons_count', 1);

        $this->getJson('/api/v1/lessons?course_id='.$course->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lesson->id);
    }

    public function test_progress_update_tracks_completed_lessons(): void
    {
        [$user, $course] = $this->studentContext();
        Sanctum::actingAs($user);

        $section = CourseSection::query()->create([
            'course_id' => $course->id,
            'title' => 'Section 1',
            'sort_order' => 1,
        ]);

        $lesson = $this->lesson($course, $section);

        $this->postJson('/api/v1/progress', [
            'lesson_id' => $lesson->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.lesson_id', $lesson->id)
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.completed_lessons', 1)
            ->assertJsonPath('data.total_lessons', 1)
            ->assertJsonPath('data.progress_percent', 100);

        $this->assertDatabaseHas('lesson_progress', [
            'student_id' => $user->student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_quiz_attempt_can_be_started_and_submitted(): void
    {
        [$user, $course] = $this->studentContext();
        Sanctum::actingAs($user);

        $question = Question::factory()->create([
            'course_id' => $course->id,
            'type' => QuestionType::SingleChoice,
            'marks' => 1,
        ]);

        $correct = QuestionOption::factory()->correct()->create([
            'question_id' => $question->id,
            'text' => 'Correct',
            'sort_order' => 0,
        ]);
        QuestionOption::factory()->create([
            'question_id' => $question->id,
            'text' => 'Wrong',
            'sort_order' => 1,
        ]);

        $quiz = Quiz::factory()->create([
            'course_id' => $course->id,
            'pass_percent' => 60,
            'max_attempts' => 2,
            'time_limit_minutes' => null,
            'show_answers_after' => ShowAnswersAfter::AfterSubmit,
        ]);
        $quiz->questions()->attach($question->id, ['sort_order' => 1]);

        $this->postJson('/api/v1/quiz-attempts', [
            'quiz_id' => $quiz->id,
            'submit' => true,
            'answers' => [
                [
                    'question_id' => $question->id,
                    'selected_option_ids' => [$correct->id],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('quiz.id', $quiz->id)
            ->assertJsonPath('attempt.status', 'graded')
            ->assertJsonPath('attempt.percent', '100.00')
            ->assertJsonPath('answers_revealed', true);
    }

    public function test_student_can_list_orders(): void
    {
        [$user, $course] = $this->studentContext();
        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'student_id' => $user->student->id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'gateway' => PaymentGateway::Fake,
        ]);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.course.id', $course->id);
    }

    public function test_other_students_data_is_not_exposed(): void
    {
        [$user] = $this->studentContext();
        $other = $this->studentUser();
        Sanctum::actingAs($other);

        StudentInvoice::factory()->create(['student_id' => $user->student->id]);

        $this->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * @return array{0: User, 1: Course, 2: Batch}
     */
    protected function studentContext(): array
    {
        $user = $this->studentUser();

        $course = Course::factory()->create([
            'is_active' => true,
            'is_published' => true,
            'delivery_mode' => DeliveryMode::Online,
        ]);

        $batch = Batch::query()->create([
            'name' => 'Online',
            'course_id' => $course->id,
            'session_id' => AcademicSession::factory()->create()->id,
            'start_date' => now()->toDateString(),
            'capacity' => 0,
            'status' => BatchStatus::Open,
        ]);

        Enrollment::query()->create([
            'student_id' => $user->student->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => EnrollmentStatus::Active,
        ]);

        return [$user, $course, $batch];
    }

    protected function studentUser(): User
    {
        $user = User::factory()->student()->create();
        $user->assignRole(RoleName::Student->value);

        Student::factory()->create(['user_id' => $user->id]);

        return $user->fresh(['student']);
    }

    protected function lesson(Course $course, CourseSection $section): Lesson
    {
        return Lesson::query()->create([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => 'Lesson 1',
            'type' => LessonType::Video,
            'video_type' => LessonVideoType::Embed,
            'video_url_or_media_id' => 'https://youtu.be/dQw4w9WgXcQ',
            'duration_seconds' => 120,
            'is_published' => true,
        ]);
    }
}
