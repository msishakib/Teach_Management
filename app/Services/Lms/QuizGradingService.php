<?php

namespace App\Services\Lms;

use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Auto-grades choice / true-false answers. Short answers stay null until a teacher grades them.
 */
class QuizGradingService
{
    public function __construct(
        protected CourseCompletionService $completions,
    ) {}

    public function gradeAttempt(QuizAttempt $attempt): QuizAttempt
    {
        return DB::transaction(function () use ($attempt): QuizAttempt {
            $attempt = QuizAttempt::query()
                ->withoutGlobalScopes()
                ->with(['quiz.questions.options', 'answers'])
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());

            if ($attempt->status === QuizAttemptStatus::InProgress) {
                throw ValidationException::withMessages([
                    'attempt' => 'Submit the attempt before grading.',
                ]);
            }

            $quiz = $attempt->quiz;
            $answersByQuestion = $attempt->answers->keyBy('question_id');
            $score = 0.0;
            $max = 0.0;
            $needsManual = false;

            foreach ($quiz->questions as $question) {
                $marks = $quiz->marksFor($question);
                $max += $marks;

                /** @var QuizAnswer|null $answer */
                $answer = $answersByQuestion->get($question->id);

                if (! $answer instanceof QuizAnswer) {
                    $answer = QuizAnswer::query()->create([
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'selected_option_ids' => [],
                        'answer_text' => null,
                        'is_correct' => false,
                        'marks_awarded' => 0,
                    ]);
                }

                if (! $question->type->isAutoGradable()) {
                    $needsManual = true;
                    $answer->forceFill([
                        'is_correct' => null,
                        'marks_awarded' => $answer->marks_awarded,
                    ])->save();

                    $score += (float) ($answer->marks_awarded ?? 0);

                    continue;
                }

                $result = $this->gradeAuto($question, $answer->selectedOptionIdList());
                $awarded = $result['is_correct'] ? $marks : 0.0;
                $score += $awarded;

                $answer->forceFill([
                    'is_correct' => $result['is_correct'],
                    'marks_awarded' => $awarded,
                ])->save();
            }

            $percent = $max > 0 ? round(($score / $max) * 100, 2) : 0.0;

            $attempt->forceFill([
                'score' => round($score, 2),
                'percent' => $percent,
                'status' => $needsManual ? QuizAttemptStatus::Submitted : QuizAttemptStatus::Graded,
            ])->save();

            $this->completions->recalculate($attempt->student_id, (int) $quiz->course_id);

            return $attempt->fresh(['answers', 'quiz']);
        });
    }

    /**
     * Teacher awards marks for a short-answer question after review.
     */
    public function awardManualMarks(QuizAnswer $answer, float $marks, ?bool $isCorrect = null): QuizAnswer
    {
        return DB::transaction(function () use ($answer, $marks, $isCorrect): QuizAnswer {
            $answer = QuizAnswer::query()->with(['attempt.quiz.questions', 'question'])->lockForUpdate()->findOrFail($answer->getKey());
            $attempt = $answer->attempt;
            $quiz = $attempt->quiz;
            $question = $answer->question;

            abort_unless($question->type === QuestionType::ShortAnswer, 422);

            $max = $quiz->marksFor($question);
            $marks = max(0, min($marks, $max));

            $answer->forceFill([
                'marks_awarded' => $marks,
                'is_correct' => $isCorrect ?? ($marks >= $max),
            ])->save();

            $this->recomputeTotals($attempt);

            return $answer->fresh();
        });
    }

    /**
     * @param  list<int>  $selectedIds
     * @return array{is_correct: bool}
     */
    public function gradeAuto(Question $question, array $selectedIds): array
    {
        $selected = collect($selectedIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $correct = collect($question->correctOptionIds())->sort()->values()->all();

        return [
            'is_correct' => $selected === $correct && $correct !== [],
        ];
    }

    protected function recomputeTotals(QuizAttempt $attempt): void
    {
        $attempt->load(['quiz.questions', 'answers']);
        $quiz = $attempt->quiz;
        $answersByQuestion = $attempt->answers->keyBy('question_id');
        $score = 0.0;
        $max = 0.0;
        $needsManual = false;

        foreach ($quiz->questions as $question) {
            $max += $quiz->marksFor($question);
            $answer = $answersByQuestion->get($question->id);

            if ($question->type === QuestionType::ShortAnswer && ($answer === null || $answer->marks_awarded === null)) {
                $needsManual = true;
            }

            $score += (float) ($answer?->marks_awarded ?? 0);
        }

        $percent = $max > 0 ? round(($score / $max) * 100, 2) : 0.0;

        $attempt->forceFill([
            'score' => round($score, 2),
            'percent' => $percent,
            'status' => $needsManual ? QuizAttemptStatus::Submitted : QuizAttemptStatus::Graded,
        ])->save();

        $this->completions->recalculate($attempt->student_id, (int) $quiz->course_id);
    }
}
