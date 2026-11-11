<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Concerns\EntersExamMarks;
use App\Jobs\ImportExamMarksCsvJob;
use App\Models\ExamSubject;
use App\Services\ExamMarksService;
use App\Support\PermissionName;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EnterExamMarks extends Page implements HasForms
{
    use EntersExamMarks;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'Teaching';

    protected static ?string $navigationLabel = 'Enter marks';

    protected static ?string $title = 'Enter exam marks';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.enter-exam-marks';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->can(PermissionName::make('exam', 'update'))
            || $user?->can(PermissionName::make('exam', 'create')));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountEntersExamMarks();
    }

    public function form(Form $form): Form
    {
        return $this->marksForm($form);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->marksHeaderActions(),
            Actions\Action::make('queueCsvImport')
                ->label('Queue CSV import')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\Select::make('exam_subject_id')
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
                        ->default(fn () => $this->data['exam_subject_id'] ?? $this->examSubjectId)
                        ->searchable()
                        ->required(),
                    Forms\Components\FileUpload::make('csv')
                        ->label('CSV file')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('exam-marks-imports')
                        ->required(),
                    Forms\Components\TextInput::make('reason')
                        ->label('Override reason')
                        ->helperText('Required when the exam is published.')
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $path = is_array($data['csv'] ?? null)
                        ? ($data['csv'][0] ?? null)
                        : ($data['csv'] ?? null);

                    if (! is_string($path) || $path === '') {
                        Notification::make()->title('CSV file is required')->danger()->send();

                        return;
                    }

                    $examSubject = ExamSubject::query()->with('exam')->findOrFail($data['exam_subject_id']);
                    $user = Auth::user();
                    $marks = app(ExamMarksService::class);

                    if (! $user || ! $marks->teacherCanAccessExamSubject($user, $examSubject)) {
                        Notification::make()->title('Not assigned to this exam subject')->danger()->send();
                        Storage::disk('local')->delete($path);

                        return;
                    }

                    ImportExamMarksCsvJob::dispatch(
                        examSubjectId: $examSubject->id,
                        actorId: $user->id,
                        disk: 'local',
                        path: $path,
                        reason: $data['reason'] ?? null,
                    );

                    Notification::make()
                        ->title('Marks CSV queued')
                        ->body('You will be notified when the import finishes.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
