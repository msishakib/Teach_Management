<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentAttempts';

    protected static ?string $title = 'Gateway log';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('gateway')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('request_payload')
                    ->label('Request')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state)
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('response_payload')
                    ->label('Response')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state)
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('request_payload')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->rows(12)
                            ->disabled(),
                        \Filament\Forms\Components\Textarea::make('response_payload')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                            ->rows(12)
                            ->disabled(),
                    ]),
            ])
            ->bulkActions([]);
    }
}
