<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Enums\GuardianRelation;
use App\Models\Guardian;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class GuardiansRelationManager extends RelationManager
{
    protected static string $relationship = 'guardians';

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function guardianFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->tel()
                ->maxLength(50),
            Forms\Components\Select::make('relation')
                ->options(GuardianRelation::class)
                ->required()
                ->native(false)
                ->default(GuardianRelation::Guardian),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->guardianFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot.relation')
                    ->label('Relation')
                    ->formatStateUsing(fn (?string $state) => GuardianRelation::tryFrom((string) $state)?->getLabel() ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addGuardian')
                    ->label('Add guardian')
                    ->icon('heroicon-o-plus')
                    ->form($this->guardianFormSchema())
                    ->action(function (array $data): void {
                        $relation = $data['relation'] ?? GuardianRelation::Guardian->value;
                        unset($data['relation']);

                        $guardian = Guardian::query()->create($data);

                        $this->getOwnerRecord()->guardians()->attach($guardian->id, [
                            'relation' => $relation instanceof GuardianRelation
                                ? $relation->value
                                : (string) $relation,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form($this->guardianFormSchema())
                    ->mutateRecordDataUsing(function (array $data, Guardian $record): array {
                        $data['relation'] = $record->pivot?->relation;

                        return $data;
                    })
                    ->using(function (array $data, Guardian $record): Guardian {
                        $relation = $data['relation'] ?? GuardianRelation::Guardian->value;
                        unset($data['relation']);

                        $record->update($data);

                        $this->getOwnerRecord()->guardians()->updateExistingPivot($record->id, [
                            'relation' => $relation instanceof GuardianRelation
                                ? $relation->value
                                : (string) $relation,
                        ]);

                        return $record;
                    }),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
