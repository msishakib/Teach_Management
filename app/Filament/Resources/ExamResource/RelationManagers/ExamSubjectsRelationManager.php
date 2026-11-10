<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Services\ExamWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ExamSubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    protected static ?string $title = 'Exam subjects';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        /** @var Exam $exam */
        $exam = $this->getOwnerRecord();

        return $form
            ->schema([
                Forms\Components\Select::make('subject_id')
                    ->label('Subject')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('exam_date')
                    ->required()
                    ->native(false)
                    ->minDate($exam->start_date)
                    ->maxDate($exam->end_date),
                Forms\Components\TimePicker::make('start_time')
                    ->required()
                    ->seconds(false),
                Forms\Components\TextInput::make('duration_minutes')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(600)
                    ->suffix('min'),
                Forms\Components\TextInput::make('full_marks')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Forms\Components\TextInput::make('pass_marks')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->lte('full_marks'),
                Forms\Components\Select::make('teacher_id')
                    ->label('Teacher / invigilator')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['subject', 'teacher']))
            ->defaultSort('exam_date')
            ->columns([
                Tables\Columns\TextColumn::make('subject.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('exam_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->suffix(' min'),
                Tables\Columns\TextColumn::make('full_marks'),
                Tables\Columns\TextColumn::make('pass_marks'),
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Teacher')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->canMutateSubjects())
                    ->using(function (array $data): ExamSubject {
                        return $this->persistSubject($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->canMutateSubjects())
                    ->using(function (ExamSubject $record, array $data): ExamSubject {
                        return $this->persistSubject($data, $record);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->canMutateSubjects()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->canMutateSubjects()),
                ]),
            ]);
    }

    protected function canMutateSubjects(): bool
    {
        /** @var Exam $exam */
        $exam = $this->getOwnerRecord();

        return in_array($exam->status, [ExamStatus::Draft, ExamStatus::Scheduled], true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistSubject(array $data, ?ExamSubject $existing = null): ExamSubject
    {
        /** @var Exam $exam */
        $exam = $this->getOwnerRecord();
        $workflow = app(ExamWorkflowService::class);

        if (isset($data['start_time']) && is_string($data['start_time']) && strlen($data['start_time']) === 5) {
            $data['start_time'] .= ':00';
        }

        try {
            if ($existing) {
                return $workflow->updateSubject($existing, $data);
            }

            return $workflow->addSubject($exam, $data);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Invalid exam subject')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
