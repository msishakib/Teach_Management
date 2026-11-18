<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Services\Lms\CourseAccessService;
use App\Services\Lms\QuizAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    public function show(
        Request $request,
        Course $course,
        Quiz $quiz,
        CourseAccessService $access,
        QuizAttemptService $attempts,
    ): View|RedirectResponse {
        abort_unless((int) $quiz->course_id === (int) $course->id, 404);
        Gate::authorize('attempt', $quiz);
        abort_unless($access->canAccessCourse($request->user(), $course), 403);

        $student = $request->user()->student;
        abort_unless($student !== null, 403);

        $open = QuizAttempt::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        $latest = QuizAttempt::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();

        return view('site.courses.quiz', [
            'course' => $course,
            'quiz' => $quiz->loadCount('questions'),
            'openAttempt' => $open,
            'latestAttempt' => $latest,
            'attemptsUsed' => $attempts->attemptsUsed($quiz, $student),
            'payload' => $open ? $attempts->studentPayload($open) : null,
            'results' => ($latest && $latest->status->isClosed())
                ? $attempts->studentPayload($latest, includeResults: true)
                : null,
        ]);
    }

    public function start(
        Request $request,
        Course $course,
        Quiz $quiz,
        QuizAttemptService $attempts,
    ): RedirectResponse {
        abort_unless((int) $quiz->course_id === (int) $course->id, 404);
        Gate::authorize('attempt', $quiz);

        $student = $request->user()->student;
        abort_unless($student !== null, 403);

        $attempt = $attempts->start($quiz, $student);

        return redirect()->route('courses.quizzes.attempts.show', [
            'course' => $course->code,
            'quiz' => $quiz,
            'attempt' => $attempt,
        ]);
    }

    public function attempt(
        Request $request,
        Course $course,
        Quiz $quiz,
        QuizAttempt $attempt,
        QuizAttemptService $attempts,
    ): View {
        abort_unless((int) $quiz->course_id === (int) $course->id, 404);
        abort_unless((int) $attempt->quiz_id === (int) $quiz->id, 404);
        Gate::authorize('view', $attempt);

        $includeResults = $attempt->status->isClosed();

        return view('site.courses.quiz-attempt', [
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $attempt,
            'payload' => $attempts->studentPayload($attempt, includeResults: $includeResults),
        ]);
    }

    public function saveAnswer(
        Request $request,
        Course $course,
        Quiz $quiz,
        QuizAttempt $attempt,
        Question $question,
        QuizAttemptService $attempts,
    ): JsonResponse {
        abort_unless((int) $quiz->course_id === (int) $course->id, 404);
        abort_unless((int) $attempt->quiz_id === (int) $quiz->id, 404);
        Gate::authorize('update', $attempt);

        $data = $request->validate([
            'selected_option_ids' => ['nullable', 'array'],
            'selected_option_ids.*' => ['integer'],
            'answer_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $answer = $attempts->saveAnswer($attempt, $question, $data);

        return response()->json([
            'saved' => true,
            'question_id' => (int) $question->id,
            'selected_option_ids' => $answer->selectedOptionIdList(),
            'answer_text' => $answer->answer_text,
            'server_now' => now()->toIso8601String(),
        ]);
    }

    public function submit(
        Request $request,
        Course $course,
        Quiz $quiz,
        QuizAttempt $attempt,
        QuizAttemptService $attempts,
    ): RedirectResponse|JsonResponse {
        abort_unless((int) $quiz->course_id === (int) $course->id, 404);
        abort_unless((int) $attempt->quiz_id === (int) $quiz->id, 404);
        Gate::authorize('update', $attempt);

        $graded = $attempts->submit($attempt);
        $payload = $attempts->studentPayload($graded, includeResults: true);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('courses.quizzes.attempts.show', [
                'course' => $course->code,
                'quiz' => $quiz,
                'attempt' => $graded,
            ])
            ->with('status', __('course.quiz.submitted'));
    }
}
