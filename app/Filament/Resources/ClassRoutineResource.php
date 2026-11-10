<?php

namespace App\Filament\Resources;

use App\Enums\WeekDay;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\ClassRoutineResource\Pages;
use App\Models\ClassRoutine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassRoutineResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = ClassRoutine::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Class routine';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('batch_id')
                    ->relationship('batch', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('subject_id')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('day')
                    ->options(WeekDay::class)
                    ->required()
                    ->native(false),
                Forms\Components\TimePicker::make('start_time')
                    ->required()
                    ->seconds(false),
                Forms\Components\TimePicker::make('end_time')
                    ->required()
                    ->seconds(false)
                    ->after('start_time'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('batch.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('teacher.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->time('H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('day')
                    ->options(WeekDay::class),
                Tables\Filters\SelectFilter::make('batch_id')
                    ->relationship('batch', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('teacher_id')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['batch', 'subject', 'teacher']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassRoutines::route('/'),
            'create' => Pages\CreateClassRoutine::route('/create'),
            'edit' => Pages\EditClassRoutine::route('/{record}/edit'),
        ];
    }
}
