<?php

namespace App\Services\Lms;

use App\Enums\QuizAttemptStatus;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;

class QuizAnalyticsService
{
    /**
     * @return array{
     *     attempts: int,
     *     average_score: float|null,
     *     average_percent: float|null,
     *     pass_rate: float|null,
     *     hardest_questions: list<array{question_id: int, text: string, correct_rate: float, answered: int}>
     * }
     */
    public function forQuiz(Quiz $quiz, int $hardestLimit = 5): array
    {
        $attemptStats = DB::table('quiz_attempts')
            ->where('quiz_id', $quiz->id)
            ->whereIn('status', [
                QuizAttemptStatus::Submitted->value,
                QuizAttemptStatus::Graded->value,
            ])
            ->selectRaw('COUNT(*) as attempts')
            ->selectRaw('AVG(score) as average_score')
            ->selectRaw('AVG(percent) as average_percent')
            ->selectRaw('AVG(CASE WHEN percent >= ? THEN 1 ELSE 0 END) as pass_rate', [(float) $quiz->pass_percent])
            ->first();

        $hardest = DB::table('quiz_answers')
            ->join('quiz_attempts', 'quiz_attempts.id', '=', 'quiz_answers.attempt_id')
            ->join('questions', 'questions.id', '=', 'quiz_answers.question_id')
            ->join('quiz_questions', function ($join) use ($quiz) {
                $join->on('quiz_questions.question_id', '=', 'questions.id')
                    ->where('quiz_questions.quiz_id', '=', $quiz->id);
            })
            ->where('quiz_attempts.quiz_id', $quiz->id)
            ->whereIn('quiz_attempts.status', [
                QuizAttemptStatus::Submitted->value,
                QuizAttemptStatus::Graded->value,
            ])
            ->whereNotNull('quiz_answers.is_correct')
            ->groupBy('questions.id', 'questions.text')
            ->orderBy('correct_rate')
            ->orderBy('questions.id')
            ->limit($hardestLimit)
            ->selectRaw('questions.id as question_id')
            ->selectRaw('questions.text as text')
            ->selectRaw('COUNT(*) as answered')
            ->selectRaw('AVG(CASE WHEN quiz_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_rate')
            ->get()
            ->map(fn ($row): array => [
                'question_id' => (int) $row->question_id,
                'text' => (string) $row->text,
                'answered' => (int) $row->answered,
                'correct_rate' => round(((float) $row->correct_rate) * 100, 2),
            ])
            ->all();

        $attempts = (int) ($attemptStats->attempts ?? 0);

        return [
            'attempts' => $attempts,
            'average_score' => $attempts > 0 ? round((float) $attemptStats->average_score, 2) : null,
            'average_percent' => $attempts > 0 ? round((float) $attemptStats->average_percent, 2) : null,
            'pass_rate' => $attempts > 0 ? round(((float) $attemptStats->pass_rate) * 100, 2) : null,
            'hardest_questions' => $hardest,
        ];
    }
}
