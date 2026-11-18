<?php

namespace App\Filament\Resources;

use App\Enums\ShowAnswersAfter;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\QuizResource\Pages;
use App\Filament\Resources\QuizResource\RelationManagers\QuestionsRelationManager;
use App\Filament\Resources\QuizResource\RelationManagers\AttemptsRelationManager;
use App\Models\Quiz;
use App\Services\Lms\QuizAnalyticsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuizResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Quiz::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('course_id')
                ->relationship('course', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live(),
            Forms\Components\Select::make('lesson_id')
                ->label('Lesson')
                ->options(function (Forms\Get $get): array {
                    $courseId = $get('course_id');

                    if (! $courseId) {
                        return [];
                    }

                    return \App\Models\Lesson::query()
                        ->where('course_id', $courseId)
                        ->orderBy('sort_order')
                        ->pluck('title', 'id')
                        ->all();
                })
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('time_limit_minutes')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->nullable(),
            Forms\Components\TextInput::make('pass_percent')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->default(60)
                ->required(),
            Forms\Components\TextInput::make('max_attempts')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->default(1)
                ->required(),
            Forms\Components\Select::make('show_answers_after')
                ->options(ShowAnswersAfter::options())
                ->default(ShowAnswersAfter::AfterSubmit->value)
                ->required()
                ->native(false),
            Forms\Components\Toggle::make('shuffle_questions')->inline(false),
            Forms\Components\Toggle::make('shuffle_options')->inline(false),
            Forms\Components\Toggle::make('one_question_per_page')->inline(false),
            Forms\Components\DateTimePicker::make('available_from'),
            Forms\Components\DateTimePicker::make('available_until'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('course.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pass_percent')->label('Pass %'),
                Tables\Columns\TextColumn::make('max_attempts'),
                Tables\Columns\TextColumn::make('questions_count')->counts('questions')->label('Questions'),
                Tables\Columns\TextColumn::make('attempts_count')->counts('attempts')->label('Attempts'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('analytics')
                    ->icon('heroicon-o-chart-bar')
                    ->modalHeading('Quiz analytics')
                    ->modalContent(function (Quiz $record) {
                        $stats = app(QuizAnalyticsService::class)->forQuiz($record);

                        return view('filament.quizzes.analytics', ['stats' => $stats, 'quiz' => $record]);
                    })
                    ->modalSubmitAction(false),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
            AttemptsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['course'])->withCount(['questions', 'attempts']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizzes::route('/'),
            'create' => Pages\CreateQuiz::route('/create'),
            'edit' => Pages\EditQuiz::route('/{record}/edit'),
        ];
    }
}
