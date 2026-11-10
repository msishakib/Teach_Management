<?php

namespace App\Filament\Resources\GradingScaleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $title = 'Grade rules';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('min_percent')
                    ->label('Min %')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\TextInput::make('max_percent')
                    ->label('Max %')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->gte('min_percent'),
                Forms\Components\TextInput::make('letter_grade')
                    ->required()
                    ->maxLength(10),
                Forms\Components\TextInput::make('grade_point')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(5)
                    ->step(0.01),
                Forms\Components\TextInput::make('remark')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('letter_grade')
            ->defaultSort('min_percent', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('letter_grade')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_percent')
                    ->label('Min %')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_percent')
                    ->label('Max %')
                    ->sortable(),
                Tables\Columns\TextColumn::make('grade_point')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remark')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
