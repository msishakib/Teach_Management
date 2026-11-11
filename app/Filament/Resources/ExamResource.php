<?php

namespace App\Filament\Resources;

use App\Enums\ExamStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\GradingScale;
use App\Services\ExamMarksService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Select::make('academic_session_id')
                    ->label('Academic session')
                    ->relationship('academicSession', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('exam_type_id')
                    ->label('Exam type')
                    ->relationship('examType', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('batch_id')
                    ->label('Batch')
                    ->relationship('batch', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('grading_scale_id')
                    ->label('Grading scale')
                    ->relationship('gradingScale', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\DatePicker::make('start_date')
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('end_date')
                    ->required()
                    ->native(false)
                    ->afterOrEqual('start_date'),
                Forms\Components\TextInput::make('fee_amount')
                    ->label('Exam fee (optional)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('৳')
                    ->helperText('When set, invoices can be generated after scheduling.'),
                Forms\Components\Select::make('status')
                    ->options(ExamStatus::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('examType.name')
                    ->label('Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('batch.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('academicSession.name')
                    ->label('Session')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fee_amount')
                    ->money('BDT')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subjects_count')
                    ->counts('subjects')
                    ->label('Subjects')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('marks_progress')
                    ->label('Marks progress')
                    ->getStateUsing(function (Exam $record): string {
                        static $cache = [];

                        if (! isset($cache[$record->id])) {
                            $cache[$record->id] = app(ExamMarksService::class)->progress($record);
                        }

                        $progress = $cache[$record->id];

                        return "{$progress['subjects_complete']}/{$progress['subjects_total']} ({$progress['percent']}%)";
                    })
                    ->badge()
                    ->color(function (Exam $record): string {
                        static $cache = [];

                        if (! isset($cache[$record->id])) {
                            $cache[$record->id] = app(ExamMarksService::class)->progress($record);
                        }

                        $percent = $cache[$record->id]['percent'];

                        return match (true) {
                            $percent >= 100 => 'success',
                            $percent > 0 => 'warning',
                            default => 'gray',
                        };
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ExamStatus::class),
                Tables\Filters\SelectFilter::make('exam_type_id')
                    ->label('Type')
                    ->options(fn () => ExamType::query()->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(fn () => Batch::query()->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('academic_session_id')
                    ->label('Session')
                    ->options(fn () => AcademicSession::query()->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('grading_scale_id')
                    ->label('Grading scale')
                    ->options(fn () => GradingScale::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\Action::make('routinePdf')
                    ->label('Routine')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Exam $record) => route('exams.routine.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ExamSubjectsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['examType', 'batch', 'academicSession', 'gradingScale'])
            ->withCount('subjects');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'create' => Pages\CreateExam::route('/create'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}
