<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use App\Models\Student;
use App\Services\ExamWorkflowService;
use App\Support\PermissionName;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Exam $record */
        return app(ExamWorkflowService::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        $workflow = app(ExamWorkflowService::class);

        return [
            Actions\Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::Draft
                    && Auth::user()?->can('update', $this->exam()))
                ->form([
                    Forms\Components\Toggle::make('generate_fees')
                        ->label('Generate exam fee invoices')
                        ->helperText('Only runs when a fee amount is set.')
                        ->default(false)
                        ->visible(fn (): bool => $this->exam()->hasFee()),
                ])
                ->requiresConfirmation()
                ->action(function (array $data) use ($workflow): void {
                    $this->runTransition(function () use ($workflow, $data): void {
                        $workflow->schedule(
                            $this->exam(),
                            (bool) ($data['generate_fees'] ?? false),
                            Auth::user(),
                        );
                    }, 'Exam scheduled');
                }),

            Actions\Action::make('start')
                ->label('Start exam')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::Scheduled
                    && Auth::user()?->can('update', $this->exam()))
                ->requiresConfirmation()
                ->action(fn () => $this->runTransition(
                    fn () => $workflow->start($this->exam(), Auth::user()),
                    'Exam marked ongoing'
                )),

            Actions\Action::make('marksEntry')
                ->label('Open marks entry')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::Ongoing
                    && Auth::user()?->can('update', $this->exam()))
                ->requiresConfirmation()
                ->action(fn () => $this->runTransition(
                    fn () => $workflow->openMarksEntry($this->exam(), Auth::user()),
                    'Marks entry opened'
                )),

            Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::MarksEntry
                    && Auth::user()?->can(PermissionName::make('exam', 'publish')))
                ->requiresConfirmation()
                ->action(fn () => $this->runTransition(
                    fn () => $workflow->publish($this->exam(), Auth::user()),
                    'Exam published'
                )),

            Actions\Action::make('close')
                ->label('Close')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::Published
                    && Auth::user()?->can('update', $this->exam()))
                ->requiresConfirmation()
                ->action(fn () => $this->runTransition(
                    fn () => $workflow->close($this->exam(), Auth::user()),
                    'Exam closed'
                )),

            Actions\Action::make('generateFees')
                ->label('Generate exam fees')
                ->icon('heroicon-o-banknotes')
                ->visible(fn (): bool => $this->exam()->hasFee()
                    && in_array($this->exam()->status, [ExamStatus::Scheduled, ExamStatus::Ongoing], true)
                    && Auth::user()?->can('update', $this->exam()))
                ->requiresConfirmation()
                ->action(function () use ($workflow): void {
                    try {
                        $result = $workflow->generateFees($this->exam(), Auth::user());
                        Notification::make()
                            ->title('Exam fees generated')
                            ->body("Created {$result['created']}, skipped {$result['skipped']}.")
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Could not generate fees')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('routinePdf')
                ->label('Print routine')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('exams.routine.pdf', $this->exam()))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->can('view', $this->exam())),

            Actions\Action::make('admitCard')
                ->label('Admit card')
                ->icon('heroicon-o-identification')
                ->visible(fn (): bool => (bool) Auth::user()?->can('view', $this->exam()))
                ->form([
                    Forms\Components\Select::make('student_id')
                        ->label('Student')
                        ->options(fn () => Student::query()
                            ->whereHas('enrollments', fn ($q) => $q
                                ->where('batch_id', $this->exam()->batch_id)
                                ->where('status', EnrollmentStatus::Active))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->redirect(route('exams.admit-card.pdf', [
                        'exam' => $this->exam(),
                        'student' => $data['student_id'],
                    ]));
                }),

            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::Draft),
        ];
    }

    protected function exam(): Exam
    {
        /** @var Exam $exam */
        $exam = $this->getRecord();

        return $exam;
    }

    protected function runTransition(callable $callback, string $successTitle): void
    {
        try {
            $callback();
            $this->record = $this->exam()->fresh() ?? $this->exam();
            Notification::make()->title($successTitle)->success()->send();
            $this->refreshFormData(['status']);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Invalid status transition')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }
}
