<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\ActivityResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?int $navigationSort = 94;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('activity_log.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Who')
                    ->placeholder('System')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('What')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->description(fn (Activity $record): ?string => $record->subject_id ? '#'.$record->subject_id : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->state(fn (Activity $record): ?string => $record->getExtraProperty('ip_address'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                    ]),
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Subject type')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->orderBy('subject_type')
                        ->pluck('subject_type')
                        ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
                        ->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn (): array => User::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Activity details')
                    ->modalContent(fn (Activity $record) => view('filament.activity-log.changes', [
                        'activity' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
