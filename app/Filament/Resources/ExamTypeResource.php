<?php

namespace App\Filament\Resources;

use App\Enums\ExamTypeStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\ExamTypeResource\Pages;
use App\Models\ExamType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExamTypeResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = ExamType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Exam type';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('weight')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
                Forms\Components\Select::make('status')
                    ->options(ExamTypeStatus::class)
                    ->required()
                    ->native(false)
                    ->default(ExamTypeStatus::Active),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ExamTypeStatus::class),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageExamTypes::route('/'),
        ];
    }
}
