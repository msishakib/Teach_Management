<?php

namespace App\Services\Lms;

use App\Enums\QuizAttemptStatus;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizAttemptService
{
    public function __construct(
        protected QuizGradingService $grading,
    ) {}

    public function start(Quiz $quiz, Student $student): QuizAttempt
    {
        if (! $quiz->isAvailable()) {
            throw ValidationException::withMessages([
                'quiz' => 'This quiz is not available right now.',
            ]);
        }

        return DB::transaction(function () use ($quiz, $student): QuizAttempt {
            $open = QuizAttempt::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->where('quiz_id', $quiz->id)
                ->where('student_id', $student->id)
                ->where('status', QuizAttemptStatus::InProgress)
                ->lockForUpdate()
                ->first();

            if ($open) {
                if ($open->isTimedOut()) {
                    $this->submit($open, force: true);

                    $open = null;
                } else {
                    return $open->load(['quiz', 'answers']);
                }
            }

            $used = QuizAttempt::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->where('quiz_id', $quiz->id)
                ->where('student_id', $student->id)
                ->whereIn('status', [
                    QuizAttemptStatus::Submitted->value,
                    QuizAttemptStatus::Graded->value,
                ])
                ->count();

            if ($used >= (int) $quiz->max_attempts) {
                throw ValidationException::withMessages([
                    'quiz' => 'You have used all attempts for this quiz.',
                ]);
            }

            $questionIds = $quiz->questions()->pluck('questions.id')->map(fn ($id) => (int) $id)->all();

            if ($quiz->shuffle_questions) {
                shuffle($questionIds);
            } else {
                $questionIds = $quiz->quizQuestions()->orderBy('sort_order')->pluck('question_id')->map(fn ($id) => (int) $id)->all();
            }

            return QuizAttempt::query()->create([
                'quiz_id' => $quiz->id,
                'student_id' => $student->id,
                'started_at' => now(),
                'status' => QuizAttemptStatus::InProgress,
                'attempt_no' => $used + 1,
                'question_order' => $questionIds,
            ]);
        });
    }

    /**
     * @param  array{selected_option_ids?: list<int>|null, answer_text?: string|null}  $payload
     */
    public function saveAnswer(QuizAttempt $attempt, Question $question, array $payload): QuizAnswer
    {
        $attempt = $this->assertWritable($attempt);

        $belongs = in_array((int) $question->id, $attempt->question_order ?? [], true)
            || $attempt->quiz->questions()->where('questions.id', $question->id)->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'question' => 'That question is not part of this quiz attempt.',
            ]);
        }

        $selected = collect($payload['selected_option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $question->type->allowsMultipleOptions() && count($selected) > 1) {
            $selected = array_slice($selected, 0, 1);
        }

        $validOptionIds = $question->options()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_values(array_intersect($selected, $validOptionIds));

        return QuizAnswer::query()->updateOrCreate(
            [
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
            ],
            [
                'selected_option_ids' => $selected,
                'answer_text' => $payload['answer_text'] ?? null,
                // Never persist correctness while the attempt is open.
                'is_correct' => null,
                'marks_awarded' => null,
            ],
        );
    }

    public function submit(QuizAttempt $attempt, bool $force = false): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $force): QuizAttempt {
            $attempt = QuizAttempt::query()
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->with(['quiz'])
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());

            if ($attempt->status->isClosed()) {
                throw ValidationException::withMessages([
                    'attempt' => 'This attempt has already been submitted.',
                ]);
            }

            if (! $force && $attempt->isTimedOut()) {
                // Past grace: still accept the submit as a forced close of remaining answers.
            }

            $attempt->forceFill([
                'submitted_at' => now(),
                'status' => QuizAttemptStatus::Submitted,
            ])->save();

            return $this->grading->gradeAttempt($attempt);
        });
    }

    public function assertWritable(QuizAttempt $attempt): QuizAttempt
    {
        $attempt->loadMissing('quiz');

        if (! $attempt->isOpen()) {
            throw ValidationException::withMessages([
                'attempt' => 'Submitted attempts cannot be edited.',
            ]);
        }

        if ($attempt->isTimedOut()) {
            $this->submit($attempt, force: true);

            throw ValidationException::withMessages([
                'attempt' => 'Time is up. Your attempt was submitted automatically.',
            ]);
        }

        return $attempt;
    }

    /**
     * Safe payload for the student browser — never includes is_correct or explanations pre-submit.
     *
     * @return array<string, mixed>
     */
    public function studentPayload(QuizAttempt $attempt, bool $includeResults = false): array
    {
        $attempt->loadMissing(['quiz.questions.options', 'answers']);
        $quiz = $attempt->quiz;
        $answers = $attempt->answers->keyBy('question_id');
        $order = $attempt->question_order ?: $quiz->questions->pluck('id')->all();
        $questionsById = $quiz->questions->keyBy('id');
        $showAnswers = $includeResults && $this->mayRevealAnswers($attempt);

        $questions = [];

        foreach ($order as $questionId) {
            /** @var Question|null $question */
            $question = $questionsById->get($questionId);

            if (! $question) {
                continue;
            }

            $options = $question->options->values();

            if ($quiz->shuffle_options && $attempt->isOpen()) {
                $options = $options->shuffle()->values();
            }

            $optionPayload = $options->map(function ($option) use ($showAnswers) {
                $row = [
                    'id' => (int) $option->id,
                    'text' => $option->text,
                ];

                if ($showAnswers) {
                    $row['is_correct'] = (bool) $option->is_correct;
                }

                return $row;
            })->all();

            $answer = $answers->get($question->id);

            $item = [
                'id' => (int) $question->id,
                'type' => $question->type->value,
                'text' => $question->text,
                'marks' => $quiz->marksFor($question),
                'options' => $optionPayload,
                'answer' => [
                    'selected_option_ids' => $answer?->selectedOptionIdList() ?? [],
                    'answer_text' => $answer?->answer_text,
                ],
            ];

            if ($showAnswers) {
                $item['explanation'] = $question->explanation;
                $item['is_correct'] = $answer?->is_correct;
                $item['marks_awarded'] = $answer?->marks_awarded !== null ? (float) $answer->marks_awarded : null;
            }

            $questions[] = $item;
        }

        $deadline = $quiz->deadlineFor($attempt->started_at);

        return [
            'attempt' => [
                'id' => (int) $attempt->id,
                'attempt_no' => (int) $attempt->attempt_no,
                'status' => $attempt->status->value,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'deadline_at' => $deadline?->toIso8601String(),
                'server_now' => now()->toIso8601String(),
                'score' => $includeResults ? $attempt->score : null,
                'percent' => $includeResults ? $attempt->percent : null,
                'passed' => $includeResults ? $attempt->passed() : null,
            ],
            'quiz' => [
                'id' => (int) $quiz->id,
                'title' => $quiz->title,
                'pass_percent' => (float) $quiz->pass_percent,
                'one_question_per_page' => (bool) $quiz->one_question_per_page,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'show_answers_after' => $quiz->show_answers_after->value,
            ],
            'questions' => $questions,
            'answers_revealed' => $showAnswers,
        ];
    }

    public function mayRevealAnswers(QuizAttempt $attempt): bool
    {
        if ($attempt->isOpen()) {
            return false;
        }

        $quiz = $attempt->relationLoaded('quiz') ? $attempt->quiz : $attempt->quiz()->first();

        return match ($quiz->show_answers_after) {
            \App\Enums\ShowAnswersAfter::Never => false,
            \App\Enums\ShowAnswersAfter::AfterSubmit => true,
            \App\Enums\ShowAnswersAfter::AfterPass => $attempt->passed(),
        };
    }

    public function attemptsUsed(Quiz $quiz, Student $student): int
    {
        return QuizAttempt::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereIn('status', [
                QuizAttemptStatus::Submitted->value,
                QuizAttemptStatus::Graded->value,
            ])
            ->count();
    }
}
