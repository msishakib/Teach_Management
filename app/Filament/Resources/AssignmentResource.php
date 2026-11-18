<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\AssignmentResource\Pages;
use App\Filament\Resources\AssignmentResource\RelationManagers\SubmissionsRelationManager;
use App\Models\Assignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Assignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 6;

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
            Forms\Components\Textarea::make('instructions')
                ->rows(6)
                ->columnSpanFull(),
            Forms\Components\DateTimePicker::make('due_at'),
            Forms\Components\TextInput::make('max_marks')
                ->numeric()
                ->minValue(0)
                ->default(100)
                ->required(),
            Forms\Components\TextInput::make('max_files')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(3)
                ->required(),
            Forms\Components\Toggle::make('allow_late')
                ->inline(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('course.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('due_at')->dateTime(),
                Tables\Columns\TextColumn::make('max_marks'),
                Tables\Columns\IconColumn::make('allow_late')->boolean(),
                Tables\Columns\TextColumn::make('submissions_count')->counts('submissions')->label('Submissions'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubmissionsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['course'])->withCount('submissions');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignments::route('/'),
            'create' => Pages\CreateAssignment::route('/create'),
            'edit' => Pages\EditAssignment::route('/{record}/edit'),
        ];
    }
}
