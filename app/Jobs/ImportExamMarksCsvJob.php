<?php

namespace App\Jobs;

use App\Models\ExamSubject;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ExamMarksImportFinished;
use App\Services\ExamMarksService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImportExamMarksCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $examSubjectId,
        public int $actorId,
        public string $disk,
        public string $path,
        public ?string $reason = null,
    ) {}

    public function handle(ExamMarksService $marks): void
    {
        $actor = User::query()->findOrFail($this->actorId);
        $examSubject = ExamSubject::query()->with('exam')->findOrFail($this->examSubjectId);

        $absolute = Storage::disk($this->disk)->path($this->path);
        $handle = fopen($absolute, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open marks CSV for import.');
        }

        $header = null;
        $rowNumber = 0;
        $imported = 0;
        $errors = [];

        try {
            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($header === null) {
                    $header = array_map(fn ($value) => strtolower(trim((string) $value)), $data);

                    continue;
                }

                if ($this->rowIsEmpty($data)) {
                    continue;
                }

                $row = $this->mapRow($header, $data);

                try {
                    $admissionNo = trim((string) ($row['admission_no'] ?? ''));
                    $student = Student::query()->where('admission_no', $admissionNo)->first();

                    if (! $student) {
                        throw ValidationException::withMessages([
                            'admission_no' => "Student [{$admissionNo}] not found.",
                        ]);
                    }

                    $isAbsent = filter_var($row['is_absent'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    $marks->saveOne(
                        actor: $actor,
                        examSubject: $examSubject,
                        studentId: $student->id,
                        attributes: [
                            'marks_obtained' => $isAbsent ? null : ($row['marks_obtained'] ?? null),
                            'is_absent' => $isAbsent,
                            'remarks' => $row['remarks'] ?? null,
                        ],
                        reason: $this->reason,
                    );

                    $imported++;
                } catch (ValidationException $exception) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'admission_no' => $row['admission_no'] ?? null,
                        'errors' => collect($exception->errors())->flatten()->all(),
                    ];
                }
            }
        } finally {
            fclose($handle);
        }

        $reportPath = null;

        if ($errors !== []) {
            $reportPath = 'exam-marks-imports/errors-'.$this->examSubjectId.'-'.now()->format('YmdHis').'.csv';
            $csv = fopen('php://temp', 'r+');
            fputcsv($csv, ['row', 'admission_no', 'errors']);

            foreach ($errors as $error) {
                fputcsv($csv, [
                    $error['row'],
                    $error['admission_no'],
                    implode('; ', $error['errors']),
                ]);
            }

            rewind($csv);
            Storage::disk($this->disk)->put($reportPath, stream_get_contents($csv) ?: '');
            fclose($csv);
        }

        Storage::disk($this->disk)->delete($this->path);

        $actor->notify(new ExamMarksImportFinished(
            examSubjectId: $this->examSubjectId,
            imported: $imported,
            failed: count($errors),
            errorReportPath: $reportPath,
            disk: $this->disk,
        ));
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $data
     * @return array<string, mixed>
     */
    protected function mapRow(array $header, array $data): array
    {
        $row = [];

        foreach ($header as $index => $column) {
            $row[$column] = $data[$index] ?? null;
        }

        return $row;
    }

    /**
     * @param  list<string|null>  $data
     */
    protected function rowIsEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
