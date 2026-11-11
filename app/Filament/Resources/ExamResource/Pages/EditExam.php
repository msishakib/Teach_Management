<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use App\Models\Student;
use App\Filament\Pages\EnterExamMarks;
use App\Models\ExamResult;
use App\Services\CertificateService;
use App\Services\ExamMarksService;
use App\Services\ExamWorkflowService;
use App\Services\ResultService;
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

            Actions\Action::make('processResults')
                ->label('Process results')
                ->icon('heroicon-o-calculator')
                ->visible(fn (): bool => in_array($this->exam()->status, [
                    ExamStatus::MarksEntry,
                    ExamStatus::Published,
                ], true) && Auth::user()?->can('update', $this->exam()))
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $outcome = app(ResultService::class)->process($this->exam(), Auth::user());
                        Notification::make()
                            ->title('Results processed')
                            ->body("Version {$outcome['version']->version}: {$outcome['processed']} students ({$outcome['passed']} pass, {$outcome['failed']} fail, {$outcome['absent']} absent).")
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Could not process results')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('publish')
                ->label('Publish results')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::MarksEntry
                    && Auth::user()?->can(PermissionName::make('exam', 'publish')))
                ->form([
                    Forms\Components\Toggle::make('notify')
                        ->label('Notify students & guardians')
                        ->default(fn () => (bool) setting('academic.notify_on_result_publish', false)),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    try {
                        app(ResultService::class)->publish(
                            $this->exam(),
                            Auth::user(),
                            (bool) ($data['notify'] ?? false),
                        );
                        $this->record = $this->exam()->fresh() ?? $this->exam();
                        Notification::make()->title('Results published')->success()->send();
                        $this->refreshFormData(['status']);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Could not publish')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('unpublish')
                ->label('Unpublish results')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::Published
                    && Auth::user()?->can(PermissionName::make('exam', 'override')))
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        app(ResultService::class)->unpublish($this->exam(), Auth::user());
                        $this->record = $this->exam()->fresh() ?? $this->exam();
                        Notification::make()->title('Results unpublished')->success()->send();
                        $this->refreshFormData(['status']);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Could not unpublish')
                            ->body(collect($e->errors())->flatten()->first())
                            ->danger()
                            ->send();
                    }
                }),

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

            Actions\Action::make('meritList')
                ->label('Merit list PDF')
                ->icon('heroicon-o-trophy')
                ->url(fn (): string => route('exams.merit-list.pdf', $this->exam()))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->can('view', $this->exam())),

            Actions\Action::make('failList')
                ->label('Fail list PDF')
                ->icon('heroicon-o-x-circle')
                ->url(fn (): string => route('exams.fail-list.pdf', $this->exam()))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->can('view', $this->exam())),

            Actions\Action::make('subjectStats')
                ->label('Subject stats PDF')
                ->icon('heroicon-o-chart-bar')
                ->url(fn (): string => route('exams.subject-stats.pdf', $this->exam()))
                ->openUrlInNewTab()
                ->visible(fn (): bool => (bool) Auth::user()?->can('view', $this->exam())),

            Actions\Action::make('issueCertificates')
                ->label('Issue certificates')
                ->icon('heroicon-o-academic-cap')
                ->visible(fn (): bool => $this->exam()->status === ExamStatus::Published
                    && Auth::user()?->can(PermissionName::make('exam', 'publish')))
                ->requiresConfirmation()
                ->action(function (): void {
                    $actor = Auth::user();
                    $certs = app(CertificateService::class);
                    $issued = 0;

                    ExamResult::withoutGlobalScopes()
                        ->with(['student', 'exam.batch.course'])
                        ->where('exam_id', $this->exam()->id)
                        ->whereNotNull('published_at')
                        ->where('status', \App\Enums\ExamResultStatus::Pass)
                        ->orderBy('id')
                        ->chunkById(100, function ($results) use ($certs, $actor, &$issued): void {
                            foreach ($results as $result) {
                                $certs->issueForExamResult($result, $actor);
                                $issued++;
                            }
                        });

                    Notification::make()
                        ->title('Certificates issued')
                        ->body("{$issued} certificate(s) ready.")
                        ->success()
                        ->send();
                }),

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

            Actions\Action::make('enterMarks')
                ->label(function (): string {
                    $progress = app(ExamMarksService::class)->progress($this->exam());

                    return "Enter marks ({$progress['subjects_complete']}/{$progress['subjects_total']})";
                })
                ->icon('heroicon-o-pencil-square')
                ->url(EnterExamMarks::getUrl(query: ['exam_id' => $this->exam()->id]))
                ->visible(fn (): bool => in_array($this->exam()->status, [
                    ExamStatus::MarksEntry,
                    ExamStatus::Published,
                    ExamStatus::Closed,
                ], true) && Auth::user()?->can('update', $this->exam())),

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
