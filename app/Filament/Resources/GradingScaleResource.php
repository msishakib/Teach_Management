<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\GradingScaleResource\Pages;
use App\Filament\Resources\GradingScaleResource\RelationManagers;
use App\Models\GradingScale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GradingScaleResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = GradingScale::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Grading scale';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_default')
                    ->label('Default scale')
                    ->helperText('Only one scale can be the default.')
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
                Tables\Columns\TextColumn::make('rules_count')
                    ->counts('rules')
                    ->label('Rules'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\RulesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('rules');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGradingScales::route('/'),
            'create' => Pages\CreateGradingScale::route('/create'),
            'edit' => Pages\EditGradingScale::route('/{record}/edit'),
        ];
    }

    public static function ensureSingleDefault(?GradingScale $scale, bool $isDefault): void
    {
        if (! $isDefault || ! $scale) {
            return;
        }

        DB::transaction(function () use ($scale) {
            GradingScale::query()
                ->where('id', '!=', $scale->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }
}
