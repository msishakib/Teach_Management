<?php

namespace App\Filament\Concerns;

use App\Enums\AttendanceSessionStatus;
use App\Enums\StudentAttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\AttendanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait TakesBulkAttendance
{
    public ?array $data = [];

    public bool $rosterLoaded = false;

    public ?int $sessionId = null;

    public ?string $sessionStatus = null;

    public bool $canOverride = false;

    public function mountTakesBulkAttendance(): void
    {
        $user = Auth::user();
        $this->canOverride = (bool) $user?->can('attendance.override');

        $this->form->fill([
            'date' => now()->toDateString(),
            'rows' => [],
        ]);
    }

    public function attendanceForm(Form $form): Form
    {
        $batches = app(AttendanceService::class)->batchesForActor(Auth::user());

        return $form
            ->schema([
                Forms\Components\Section::make('Session')
                    ->schema([
                        Forms\Components\Select::make('batch_id')
                            ->label('Batch')
                            ->options($batches->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetRoster()),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetRoster()),
                        Forms\Components\Select::make('subject_id')
                            ->label('Subject (optional)')
                            ->options(Subject::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->resetRoster()),
                        Forms\Components\Select::make('teacher_id')
                            ->label('Teacher')
                            ->options(Teacher::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->default(fn () => Auth::user()?->teacher?->id),
                        Forms\Components\TimePicker::make('start_time')
                            ->seconds(false)
                            ->nullable(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Roster')
                    ->description(fn () => $this->sessionStatus
                        ? 'Session status: '.ucfirst((string) $this->sessionStatus)
                        : 'Load enrolled active students, then toggle exceptions from the default Present status.')
                    ->schema([
                        Forms\Components\Repeater::make('rows')
                            ->label('')
                            ->schema([
                                Forms\Components\Hidden::make('student_id'),
                                Forms\Components\Placeholder::make('student_label')
                                    ->label('Student')
                                    ->content(fn (?array $state, Forms\Get $get) => $get('label') ?? '—'),
                                Forms\Components\Hidden::make('label'),
                                Forms\Components\Select::make('status')
                                    ->options(StudentAttendanceStatus::class)
                                    ->required()
                                    ->native(false)
                                    ->disablePlaceholderSelection(),
                                Forms\Components\TextInput::make('remarks')
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->visible(fn () => $this->rosterLoaded),
                    ]),
            ])
            ->statePath('data');
    }

    public function loadRoster(AttendanceService $attendance): void
    {
        $state = $this->form->getState();
        $batch = Batch::query()->findOrFail($state['batch_id']);
        $user = Auth::user();

        if (! $user || ! $attendance->teacherCanAccessBatch($user, $batch)) {
            Notification::make()->title('Not assigned to this batch')->danger()->send();

            return;
        }

        $date = \Illuminate\Support\Carbon::parse($state['date']);
        $subjectId = $state['subject_id'] ?? null;

        $students = $attendance->loadRoster($batch, $date, $subjectId ? (int) $subjectId : null);

        $session = AttendanceSession::query()
            ->where('batch_id', $batch->id)
            ->whereDate('date', $date->toDateString())
            ->when(
                $subjectId === null,
                fn ($q) => $q->whereNull('subject_id'),
                fn ($q) => $q->where('subject_id', $subjectId),
            )
            ->first();

        $this->sessionId = $session?->id;
        $this->sessionStatus = $session?->status?->value;

        $rows = $students->map(function ($student) {
            $default = StudentAttendanceStatus::Present->value;

            if ((int) ($student->on_approved_leave ?? 0) === 1) {
                $default = StudentAttendanceStatus::Excused->value;
            }

            $status = $student->attendance_status ?: $default;

            return [
                'student_id' => $student->id,
                'label' => "{$student->admission_no} — {$student->name}",
                'status' => $status,
                'remarks' => $student->attendance_remarks,
            ];
        })->values()->all();

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

    public function saveAttendance(AttendanceService $attendance, bool $finalize = false): void
    {
        try {
            $state = $this->form->getState();
            $batch = Batch::query()->findOrFail($state['batch_id']);
            $user = Auth::user();

            $session = $attendance->saveBulk(
                actor: $user,
                batch: $batch,
                date: \Illuminate\Support\Carbon::parse($state['date']),
                rows: $state['rows'] ?? [],
                subjectId: isset($state['subject_id']) ? (int) $state['subject_id'] : null,
                teacherId: isset($state['teacher_id']) ? (int) $state['teacher_id'] : null,
                startTime: $state['start_time'] ?? null,
                finalize: $finalize,
            );

            $this->sessionId = $session->id;
            $this->sessionStatus = $session->status->value;

            Notification::make()
                ->title($finalize ? 'Attendance saved and finalized' : 'Attendance saved')
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Could not save attendance')
                ->danger()
                ->send();
        }
    }

    public function finalizeAttendance(AttendanceService $attendance): void
    {
        $this->saveAttendance($attendance, finalize: true);
    }

    protected function resetRoster(): void
    {
        $this->rosterLoaded = false;
        $this->sessionId = null;
        $this->sessionStatus = null;
        $state = $this->form->getRawState();
        $this->form->fill([
            ...$state,
            'rows' => [],
        ]);
    }

    public function isReadonlySession(): bool
    {
        return $this->sessionStatus === AttendanceSessionStatus::Finalized->value
            && ! $this->canOverride;
    }
}
