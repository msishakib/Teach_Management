<?php

namespace App\Filament\Imports;

use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Services\ExamMarksService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExamMarkImporter extends Importer
{
    protected static ?string $model = ExamMark::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('admission_no')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50']),
            ImportColumn::make('student_name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('marks_obtained')
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('is_absent')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('remarks')
                ->rules(['nullable', 'string', 'max:255']),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('exam_subject_id')
                ->label('Exam subject')
                ->options(function () {
                    $user = Auth::user();
                    $marks = app(ExamMarksService::class);

                    return $marks->examsForActor($user)
                        ->load(['subjects.subject'])
                        ->flatMap(fn ($exam) => $marks->subjectsForActor($user, $exam)
                            ->mapWithKeys(fn (ExamSubject $subject) => [
                                $subject->id => $exam->name.' — '.($subject->subject?->name ?? 'Subject'),
                            ]))
                        ->all();
                })
                ->searchable()
                ->required(),
            TextInput::make('reason')
                ->label('Override reason')
                ->helperText('Required if the exam is already published.')
                ->maxLength(255),
        ];
    }

    public function resolveRecord(): ?ExamMark
    {
        $examSubjectId = (int) ($this->options['exam_subject_id'] ?? 0);
        $admissionNo = (string) ($this->data['admission_no'] ?? '');

        $student = Student::query()->where('admission_no', $admissionNo)->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'admission_no' => "Student with admission no [{$admissionNo}] was not found.",
            ]);
        }

        $this->data['resolved_student_id'] = $student->id;

        return ExamMark::query()->firstOrNew([
            'exam_subject_id' => $examSubjectId,
            'student_id' => $student->id,
        ]);
    }

    public function saveRecord(): void
    {
        $examSubjectId = (int) ($this->options['exam_subject_id'] ?? 0);
        $examSubject = ExamSubject::query()->with('exam')->find($examSubjectId);

        if (! $examSubject) {
            throw ValidationException::withMessages([
                'exam_subject_id' => 'Exam subject is required.',
            ]);
        }

        $studentId = (int) ($this->data['resolved_student_id'] ?? $this->record->student_id);
        $isAbsent = filter_var($this->data['is_absent'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->record = app(ExamMarksService::class)->saveOne(
            actor: Auth::user() ?? $this->import->user,
            examSubject: $examSubject,
            studentId: $studentId,
            attributes: [
                'marks_obtained' => $isAbsent ? null : ($this->data['marks_obtained'] ?? null),
                'is_absent' => $isAbsent,
                'remarks' => $this->data['remarks'] ?? null,
            ],
            reason: $this->options['reason'] ?? null,
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Exam marks import completed: '.$import->successful_rows.' row(s) imported.';

        if ($failed = $import->getFailedRowsCount()) {
            $body .= " {$failed} row(s) failed validation — download the error report from the import notification.";
        }

        return $body;
    }

    public function getJobRetryUntil(): ?\Carbon\CarbonInterface
    {
        return now()->addDay();
    }
}
