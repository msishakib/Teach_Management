<?php

namespace App\Services\Lms;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CSV columns: type, text, explanation, marks, difficulty, option_1, option_1_correct, option_2, option_2_correct, ...
 * True/false rows may use option_1=True / option_2=False with correct flags.
 * Short answers omit options.
 */
class QuestionCsvImportService
{
    /**
     * @return array{imported: int, question_ids: list<int>}
     */
    public function import(Course $course, string $csvContents): array
    {
        $rows = $this->parse($csvContents);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file has no data rows.',
            ]);
        }

        return DB::transaction(function () use ($course, $rows): array {
            $ids = [];

            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $type = QuestionType::tryFrom((string) ($row['type'] ?? ''));

                if (! $type) {
                    throw ValidationException::withMessages([
                        'file' => "Line {$line}: invalid type.",
                    ]);
                }

                $text = trim((string) ($row['text'] ?? ''));

                if ($text === '') {
                    throw ValidationException::withMessages([
                        'file' => "Line {$line}: text is required.",
                    ]);
                }

                $difficulty = QuestionDifficulty::tryFrom((string) ($row['difficulty'] ?? QuestionDifficulty::Medium->value))
                    ?? QuestionDifficulty::Medium;

                $question = Question::query()->create([
                    'course_id' => $course->id,
                    'type' => $type,
                    'text' => $text,
                    'explanation' => ($row['explanation'] ?? null) ?: null,
                    'marks' => (float) ($row['marks'] ?? 1),
                    'difficulty' => $difficulty,
                ]);

                $options = $this->extractOptions($row);

                if ($type === QuestionType::ShortAnswer && $options !== []) {
                    throw ValidationException::withMessages([
                        'file' => "Line {$line}: short_answer questions cannot have options.",
                    ]);
                }

                if ($type !== QuestionType::ShortAnswer && $options === []) {
                    throw ValidationException::withMessages([
                        'file' => "Line {$line}: at least one option is required.",
                    ]);
                }

                if ($type !== QuestionType::ShortAnswer && ! collect($options)->contains(fn ($o) => $o['is_correct'])) {
                    throw ValidationException::withMessages([
                        'file' => "Line {$line}: mark at least one correct option.",
                    ]);
                }

                foreach ($options as $sort => $option) {
                    QuestionOption::query()->create([
                        'question_id' => $question->id,
                        'text' => $option['text'],
                        'is_correct' => $option['is_correct'],
                        'sort_order' => $sort,
                    ]);
                }

                $ids[] = (int) $question->id;
            }

            return ['imported' => count($ids), 'question_ids' => $ids];
        });
    }

    /**
     * @return list<array<string, string>>
     */
    protected function parse(string $csvContents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContents)) ?: [];

        if ($lines === [] || trim($lines[0]) === '') {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);
            $row = [];

            foreach ($header as $i => $key) {
                $row[$key] = (string) ($cells[$i] ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return list<array{text: string, is_correct: bool}>
     */
    protected function extractOptions(array $row): array
    {
        $options = [];

        for ($i = 1; $i <= 12; $i++) {
            $text = trim((string) ($row["option_{$i}"] ?? ''));

            if ($text === '') {
                continue;
            }

            $correctRaw = strtolower(trim((string) ($row["option_{$i}_correct"] ?? '0')));
            $isCorrect = in_array($correctRaw, ['1', 'true', 'yes', 'y'], true);

            $options[] = [
                'text' => $text,
                'is_correct' => $isCorrect,
            ];
        }

        return $options;
    }
}
