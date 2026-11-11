<?php

namespace App\Filament\Concerns;

use App\Enums\ExamStatus;
use App\Filament\Imports\ExamMarkImporter;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Services\ExamMarksService;
use App\Support\PermissionName;
use Filament\Actions\Action;
use Filament\Actions\ImportAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait EntersExamMarks
{
    public ?array $data = [];

    public bool $rosterLoaded = false;

    public ?int $examSubjectId = null;

    public ?string $examStatus = null;

    public bool $canOverride = false;

    public bool $requiresReason = false;

    public string $progressLabel = '';

    public function mountEntersExamMarks(?int $examId = null): void
    {
        $user = Auth::user();
        $this->canOverride = (bool) $user?->can(PermissionName::make('exam', 'override'));

        $this->form->fill([
            'exam_id' => $examId,
            'rows' => [],
            'reason' => null,
        ]);

        if ($examId) {
            $this->refreshProgress(Exam::query()->find($examId));
        }
    }

    public function marksForm(Form $form): Form
    {
        $marks = app(ExamMarksService::class);
        $exams = $marks->examsForActor(Auth::user());

        return $form
            ->schema([
                Forms\Components\Section::make('Exam subject')
                    ->schema([
                        Forms\Components\Select::make('exam_id')
                            ->label('Exam')
                            ->options($exams->mapWithKeys(fn (Exam $exam) => [
                                $exam->id => $exam->name.' ('.$exam->status->getLabel().')',
                            ]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?int $state) use ($marks): void {
                                $this->resetRoster();
                                $this->data['exam_subject_id'] = null;
                                $this->refreshProgress($state ? Exam::query()->find($state) : null);
                            }),
                        Forms\Components\Select::make('exam_subject_id')
                            ->label('Subject')
                            ->options(function (Forms\Get $get) use ($marks) {
                                $examId = $get('exam_id');

                                if (! $examId) {
                                    return [];
                                }

                                $exam = Exam::query()->find($examId);

                                if (! $exam) {
                                    return [];
                                }

                                return $marks->subjectsForActor(Auth::user(), $exam)
                                    ->mapWithKeys(fn (ExamSubject $subject) => [
                                        $subject->id => ($subject->subject?->name ?? 'Subject')
                                            .' — full '.$subject->full_marks,
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetRoster()),
                        Forms\Components\Placeholder::make('progress')
                            ->label('Marks progress')
                            ->content(fn () => $this->progressLabel !== '' ? $this->progressLabel : 'Select an exam to see progress.'),
                        Forms\Components\TextInput::make('reason')
                            ->label('Override reason')
                            ->helperText('Required when editing marks after publish.')
                            ->visible(fn () => $this->requiresReason)
                            ->required(fn () => $this->requiresReason)
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Marks grid')
                    ->description(fn () => $this->examStatus
                        ? 'Exam status: '.ucfirst(str_replace('_', ' ', (string) $this->examStatus))
                        : 'Load enrolled students, then enter marks or mark absent.')
                    ->schema([
                        Forms\Components\Repeater::make('rows')
                            ->label('')
                            ->schema([
                                Forms\Components\Hidden::make('student_id'),
                                Forms\Components\Placeholder::make('student_label')
                                    ->label('Student')
                                    ->content(fn (Forms\Get $get) => $get('label') ?? '—'),
                                Forms\Components\Hidden::make('label'),
                                Forms\Components\TextInput::make('marks_obtained')
                                    ->label('Marks')
                                    ->numeric()
                                    ->minValue(0)
                                    ->disabled(fn (Forms\Get $get) => (bool) $get('is_absent') || $this->isReadonlyGrid()),
                                Forms\Components\Toggle::make('is_absent')
                                    ->label('Absent')
                                    ->inline(false)
                                    ->live()
                                    ->disabled(fn () => $this->isReadonlyGrid())
                                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                        if ($state) {
                                            $set('marks_obtained', null);
                                        }
                                    }),
                                Forms\Components\TextInput::make('remarks')
                                    ->maxLength(255)
                                    ->disabled(fn () => $this->isReadonlyGrid()),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->visible(fn () => $this->rosterLoaded),
                    ]),
            ])
            ->statePath('data');
    }

    public function loadRoster(ExamMarksService $marks): void
    {
        $state = $this->form->getState();
        $examSubject = ExamSubject::query()->with('exam')->findOrFail($state['exam_subject_id']);
        $user = Auth::user();

        if (! $user || ! $marks->teacherCanAccessExamSubject($user, $examSubject)) {
            Notification::make()->title('Not assigned to this exam subject')->danger()->send();

            return;
        }

        $this->examSubjectId = $examSubject->id;
        $this->examStatus = $examSubject->exam->status->value;
        $this->requiresReason = $marks->requiresOverride($examSubject->exam);
        $this->refreshProgress($examSubject->exam);

        $students = $marks->loadRoster($examSubject);

        $rows = $students->map(fn ($student) => [
            'student_id' => $student->id,
            'label' => "{$student->admission_no} — {$student->name}",
            'marks_obtained' => $student->marks_obtained,
            'is_absent' => (bool) ((int) ($student->is_absent ?? 0) === 1),
            'remarks' => $student->mark_remarks,
        ])->values()->all();

        $this->form->fill([
            ...$state,
            'rows' => $rows,
        ]);

        $this->rosterLoaded = true;

        Notification::make()
            ->title(count($rows).' students loaded')
            ->success()
            ->send();
    }

    public function saveMarks(ExamMarksService $marks): void
    {
        try {
            $state = $this->form->getState();
            $examSubject = ExamSubject::query()->with('exam')->findOrFail($state['exam_subject_id']);
            $user = Auth::user();

            $result = $marks->saveBulk(
                actor: $user,
                examSubject: $examSubject,
                rows: $state['rows'] ?? [],
                reason: $state['reason'] ?? null,
            );

            $this->examStatus = $examSubject->exam->status->value;
            $this->requiresReason = $marks->requiresOverride($examSubject->exam);
            $this->refreshProgress($examSubject->exam);

            Notification::make()
                ->title('Marks saved')
                ->body("Saved {$result['saved']} row(s) ({$result['created']} new, {$result['updated']} updated).")
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Could not save marks')
                ->danger()
                ->send();
        }
    }

    public function downloadTemplate(ExamMarksService $marks): StreamedResponse
    {
        $state = $this->form->getRawState();
        $examSubjectId = $state['exam_subject_id'] ?? $this->examSubjectId;

        abort_unless($examSubjectId, 422, 'Select an exam subject first.');

        $examSubject = ExamSubject::query()->with('exam')->findOrFail($examSubjectId);
        abort_unless(
            Auth::user() && $marks->teacherCanAccessExamSubject(Auth::user(), $examSubject),
            403
        );

        $rows = $marks->csvTemplateRows($examSubject);
        $filename = 'exam-marks-template-'.$examSubject->id.'.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function marksHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ExamMarkImporter::class)
                ->chunkSize(100)
                ->csvDelimiter(',')
                ->options(fn (): array => [
                    'exam_subject_id' => $this->data['exam_subject_id'] ?? $this->examSubjectId,
                    'reason' => $this->data['reason'] ?? null,
                ])
                ->visible(fn (): bool => (bool) Auth::user()?->can(PermissionName::make('exam', 'update'))),
        ];
    }

    public function isReadonlyGrid(): bool
    {
        if ($this->examStatus === ExamStatus::MarksEntry->value) {
            return false;
        }

        if (in_array($this->examStatus, [ExamStatus::Published->value, ExamStatus::Closed->value], true)) {
            return ! $this->canOverride;
        }

        return true;
    }

    protected function resetRoster(): void
    {
        $this->rosterLoaded = false;
        $this->examSubjectId = null;
        $this->examStatus = null;
        $this->requiresReason = false;
        $state = $this->form->getRawState();
        $this->form->fill([
            ...$state,
            'rows' => [],
        ]);
    }

    protected function refreshProgress(?Exam $exam): void
    {
        if (! $exam) {
            $this->progressLabel = '';

            return;
        }

        $progress = app(ExamMarksService::class)->progress($exam);
        $this->progressLabel = sprintf(
            '%d / %d subjects complete (%s%%) — %d enrolled students',
            $progress['subjects_complete'],
            $progress['subjects_total'],
            $progress['percent'],
            $progress['enrolled'],
        );
    }
}
