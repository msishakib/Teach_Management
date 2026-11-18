<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreQuizAttemptRequest;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Services\Lms\CourseAccessService;
use App\Services\Lms\QuizAttemptService;
use Illuminate\Support\Facades\Gate;

class QuizAttemptController extends Controller
{
    public function store(
        StoreQuizAttemptRequest $request,
        CourseAccessService $access,
        QuizAttemptService $attempts,
    ) {
        $user = $request->user();
        $student = $user?->student;

        abort_unless($student !== null, 403, 'Only students can take quizzes.');

        if ($request->filled('attempt_id')) {
            $attempt = QuizAttempt::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->where('student_id', $student->id)
                ->findOrFail((int) $request->integer('attempt_id'));

            Gate::authorize('update', $attempt);

            $quiz = $attempt->quiz()->firstOrFail();
        } else {
            $quiz = Quiz::query()->findOrFail((int) $request->integer('quiz_id'));

            Gate::authorize('attempt', $quiz);
            abort_unless($access->canAccessCourse($user, (int) $quiz->course_id), 403);

            $attempt = $attempts->start($quiz, $student);
        }

        foreach ((array) $request->input('answers', []) as $answer) {
            $question = Question::query()->findOrFail((int) $answer['question_id']);

            $attempts->saveAnswer($attempt, $question, [
                'selected_option_ids' => $answer['selected_option_ids'] ?? null,
                'answer_text' => $answer['answer_text'] ?? null,
            ]);
        }

        if ($request->boolean('submit')) {
            $attempt = $attempts->submit($attempt);
        }

        $includeResults = ! $attempt->status->isClosed()
            ? false
            : true;

        return response()->json(
            $attempts->studentPayload($attempt, includeResults: $includeResults)
        );
    }
}
