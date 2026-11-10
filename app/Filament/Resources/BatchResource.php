<?php

namespace App\Filament\Resources;

use App\Enums\BatchStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\BatchResource\Pages;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BatchResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Batch::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('session_id')
                    ->label('Academic session')
                    ->relationship('session', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('end_date')
                    ->native(false)
                    ->afterOrEqual('start_date'),
                Forms\Components\TextInput::make('capacity')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\Select::make('status')
                    ->options(BatchStatus::class)
                    ->required()
                    ->native(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('course.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('session.name')
                    ->label('Session')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(BatchStatus::class),
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::query()->orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('session_id')
                    ->label('Session')
                    ->options(fn () => AcademicSession::query()->orderBy('name')->pluck('name', 'id')),
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
        return parent::getEloquentQuery()->with(['course', 'session']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBatches::route('/'),
            'create' => Pages\CreateBatch::route('/create'),
            'edit' => Pages\EditBatch::route('/{record}/edit'),
        ];
    }
}
