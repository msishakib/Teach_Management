<?php

namespace Tests\Feature;

use App\Enums\DeliveryMode;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Enums\RoleName;
use App\Enums\ShowAnswersAfter;
use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\Student;
use App\Models\User;
use App\Services\Lms\AssignmentSubmissionService;
use App\Services\Lms\CourseCompletionService;
use App\Services\Lms\QuizAttemptService;
use App\Services\Lms\QuizGradingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuizAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_timer_enforcement_rejects_answers_after_limit_plus_grace(): void
    {
        [$course, $quiz, $question, $user] = $this->quizFixture([
            'time_limit_minutes' => 1,
            'max_attempts' => 2,
        ]);

        $attempt = app(QuizAttemptService::class)->start($quiz, $user->student);

        $this->travel(1)->minutes();
        $this->travel(Quiz::TIMER_GRACE_SECONDS + 1)->seconds();

        $this->expectException(ValidationException::class);

        try {
            app(QuizAttemptService::class)->saveAnswer($attempt->fresh(), $question, [
                'selected_option_ids' => [$question->options()->where('is_correct', true)->value('id')],
            ]);
        } finally {
            $attempt->refresh();
            $this->assertTrue($attempt->status->isClosed());
        }
    }

    public function test_max_attempts_are_enforced(): void
    {
        [$course, $quiz, $question, $user] = $this->quizFixture([
            'max_attempts' => 1,
            'time_limit_minutes' => null,
        ]);

        $service = app(QuizAttemptService::class);
        $attempt = $service->start($quiz, $user->student);
        $correct = $question->options()->where('is_correct', true)->value('id');
        $service->saveAnswer($attempt, $question, ['selected_option_ids' => [$correct]]);
        $service->submit($attempt);

        $this->expectException(ValidationException::class);
        $service->start($quiz, $user->student);
    }

    public function test_correct_answers_are_not_leaked_before_submission(): void
    {
        [$course, $quiz, $question, $user] = $this->quizFixture([
            'show_answers_after' => ShowAnswersAfter::AfterSubmit,
        ]);

        $service = app(QuizAttemptService::class);
        $attempt = $service->start($quiz, $user->student);
        $payload = $service->studentPayload($attempt);

        $this->assertFalse($payload['answers_revealed']);
        $this->assertArrayNotHasKey('is_correct', $payload['questions'][0]['options'][0]);
        $this->assertArrayNotHasKey('explanation', $payload['questions'][0]);

        $this->actingAs($user)
            ->get(route('courses.quizzes.attempts.show', [
                'locale' => 'en',
                'course' => $course->code,
                'quiz' => $quiz,
                'attempt' => $attempt,
            ]))
            ->assertOk()
            ->assertDontSee('SECRET_EXPLANATION_TOKEN');
    }

    public function test_auto_grading_scores_choice_questions_correctly(): void
    {
        $course = $this->publishedCourse();
        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $single = Question::factory()->create([
            'course_id' => $course->id,
            'type' => QuestionType::SingleChoice,
            'marks' => 2,
            'text' => 'Capital of France?',
        ]);
        $correct = QuestionOption::factory()->correct()->create(['question_id' => $single->id, 'text' => 'Paris', 'sort_order' => 0]);
        QuestionOption::factory()->create(['question_id' => $single->id, 'text' => 'Lyon', 'sort_order' => 1]);

        $multi = Question::factory()->multipleChoice()->create([
            'course_id' => $course->id,
            'marks' => 2,
            'text' => 'Even numbers?',
        ]);
        $m1 = QuestionOption::factory()->correct()->create(['question_id' => $multi->id, 'text' => '2', 'sort_order' => 0]);
        $m2 = QuestionOption::factory()->correct()->create(['question_id' => $multi->id, 'text' => '4', 'sort_order' => 1]);
        QuestionOption::factory()->create(['question_id' => $multi->id, 'text' => '3', 'sort_order' => 2]);

        $tf = Question::factory()->trueFalse()->create([
            'course_id' => $course->id,
            'marks' => 1,
            'text' => 'Sky is blue?',
        ]);
        $true = QuestionOption::factory()->correct()->create(['question_id' => $tf->id, 'text' => 'True', 'sort_order' => 0]);
        QuestionOption::factory()->create(['question_id' => $tf->id, 'text' => 'False', 'sort_order' => 1]);

        $short = Question::factory()->shortAnswer()->create([
            'course_id' => $course->id,
            'marks' => 5,
            'text' => 'Explain photosynthesis',
        ]);

        $quiz = Quiz::factory()->create([
            'course_id' => $course->id,
            'pass_percent' => 50,
            'max_attempts' => 3,
            'time_limit_minutes' => null,
        ]);
        $quiz->questions()->attach([
            $single->id => ['sort_order' => 1],
            $multi->id => ['sort_order' => 2],
            $tf->id => ['sort_order' => 3],
            $short->id => ['sort_order' => 4],
        ]);

        $service = app(QuizAttemptService::class);
        $attempt = $service->start($quiz, $user->student);
        $service->saveAnswer($attempt, $single, ['selected_option_ids' => [$correct->id]]);
        $service->saveAnswer($attempt, $multi, ['selected_option_ids' => [$m1->id, $m2->id]]);
        $service->saveAnswer($attempt, $tf, ['selected_option_ids' => [$true->id]]);
        $service->saveAnswer($attempt, $short, ['answer_text' => 'Plants make food']);

        $graded = $service->submit($attempt);

        $this->assertSame(QuizAttemptStatus::Submitted, $graded->status);
        $this->assertEquals(5.0, (float) $graded->score);
        $this->assertEquals(50.0, (float) $graded->percent);

        $shortAnswer = $graded->answers()->where('question_id', $short->id)->first();
        $this->assertNull($shortAnswer->is_correct);
        $this->assertNull($shortAnswer->marks_awarded);

        app(QuizGradingService::class)->awardManualMarks($shortAnswer, 5);
        $graded->refresh();
        $this->assertSame(QuizAttemptStatus::Graded, $graded->status);
        $this->assertEquals(10.0, (float) $graded->score);
        $this->assertEquals(100.0, (float) $graded->percent);
    }

    public function test_student_cannot_read_another_students_assignment_submission(): void
    {
        Storage::fake('private');

        $course = $this->publishedCourse();
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'allow_late' => true,
            'max_files' => 2,
        ]);

        $owner = $this->studentUser();
        $intruder = $this->studentUser();
        $this->enroll($course, $owner->student);
        $this->enroll($course, $intruder->student);

        $submission = app(AssignmentSubmissionService::class)->submit(
            $assignment,
            $owner->student,
            'My essay',
            [UploadedFile::fake()->createWithContent('work.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF")],
        );

        $media = $submission->files()->first();
        $this->assertNotNull($media);

        // Global scope hides foreign submissions during route binding (404) or policy denies (403).
        $this->actingAs($intruder)
            ->get(route('assignments.submissions.download', [
                'submission' => $submission,
                'media' => $media,
            ]))
            ->assertStatus(404);

        $this->actingAs($owner)
            ->get(route('assignments.submissions.download', [
                'submission' => $submission,
                'media' => $media,
            ]))
            ->assertOk();

        $this->actingAs($intruder);
        $this->assertFalse(
            AssignmentSubmission::query()->whereKey($submission->id)->exists()
        );

        $this->actingAs($owner);
        $this->assertTrue(
            AssignmentSubmission::query()->whereKey($submission->id)->exists()
        );
    }

    public function test_quiz_and_assignment_results_feed_course_completion(): void
    {
        Storage::fake('private');

        $course = $this->publishedCourse();
        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $question = Question::factory()->create([
            'course_id' => $course->id,
            'type' => QuestionType::SingleChoice,
            'marks' => 10,
        ]);
        $correct = QuestionOption::factory()->correct()->create(['question_id' => $question->id, 'text' => 'A']);
        QuestionOption::factory()->create(['question_id' => $question->id, 'text' => 'B']);

        $quiz = Quiz::factory()->create([
            'course_id' => $course->id,
            'pass_percent' => 60,
            'max_attempts' => 2,
            'time_limit_minutes' => null,
        ]);
        $quiz->questions()->attach($question->id, ['sort_order' => 1]);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'max_marks' => 100,
            'allow_late' => true,
        ]);

        $completions = app(CourseCompletionService::class);
        $this->assertFalse($completions->requirementsMet($user->student->id, $course));

        $attempts = app(QuizAttemptService::class);
        $attempt = $attempts->start($quiz, $user->student);
        $attempts->saveAnswer($attempt, $question, ['selected_option_ids' => [$correct->id]]);
        $attempts->submit($attempt);

        $this->assertTrue($completions->hasPassedQuiz($user->student->id, $quiz));
        $this->assertFalse($completions->isComplete($user->student, $course));

        $submission = app(AssignmentSubmissionService::class)->submit(
            $assignment,
            $user->student,
            'Done',
            [],
        );

        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        app(AssignmentSubmissionService::class)->grade($submission, $admin, 80);

        $this->assertTrue($completions->isComplete($user->student, $course));
    }

    public function test_late_assignment_blocked_when_not_allowed(): void
    {
        $course = $this->publishedCourse();
        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'due_at' => now()->subDay(),
            'allow_late' => false,
        ]);

        $this->expectException(ValidationException::class);
        app(AssignmentSubmissionService::class)->submit($assignment, $user->student, 'Too late', []);
    }

    /**
     * @return array{0: Course, 1: Quiz, 2: Question, 3: User}
     */
    protected function quizFixture(array $quizAttrs = []): array
    {
        $course = $this->publishedCourse();
        $user = $this->studentUser();
        $this->enroll($course, $user->student);

        $question = Question::factory()->create([
            'course_id' => $course->id,
            'type' => QuestionType::SingleChoice,
            'marks' => 1,
            'explanation' => 'SECRET_EXPLANATION_TOKEN',
        ]);
        QuestionOption::factory()->correct()->create(['question_id' => $question->id, 'text' => 'Yes', 'sort_order' => 0]);
        QuestionOption::factory()->create(['question_id' => $question->id, 'text' => 'No', 'sort_order' => 1]);

        $quiz = Quiz::factory()->create(array_merge([
            'course_id' => $course->id,
            'pass_percent' => 60,
            'max_attempts' => 2,
            'time_limit_minutes' => 10,
            'show_answers_after' => ShowAnswersAfter::AfterSubmit,
        ], $quizAttrs));

        $quiz->questions()->attach($question->id, ['sort_order' => 1]);

        return [$course, $quiz->fresh(), $question->fresh(['options']), $user];
    }

    protected function publishedCourse(array $attrs = []): Course
    {
        return Course::factory()->create(array_merge([
            'is_active' => true,
            'is_published' => true,
            'delivery_mode' => DeliveryMode::Online,
        ], $attrs));
    }

    protected function studentUser(): User
    {
        $user = User::factory()->student()->create();
        $user->assignRole(RoleName::Student->value);
        Student::factory()->create(['user_id' => $user->id]);

        return $user->fresh(['student']);
    }

    protected function enroll(Course $course, Student $student, array $attrs = []): Enrollment
    {
        $batch = Batch::query()->firstOrCreate(
            ['course_id' => $course->id, 'name' => 'Online'],
            [
                'session_id' => AcademicSession::factory()->create()->id,
                'start_date' => now()->toDateString(),
                'capacity' => 0,
                'status' => \App\Enums\BatchStatus::Open,
            ],
        );

        return Enrollment::query()->withoutGlobalScope(PortalStudentOwnedScope::class)->create(array_merge([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => EnrollmentStatus::Active,
            'enrolled_via' => EnrollmentSource::Online,
        ], $attrs));
    }
}
