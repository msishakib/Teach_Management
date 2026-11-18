<?php

namespace App\Filament\Resources;

use App\Enums\MediaCategory;
use App\Enums\MediaType;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\MediaResource\Pages;
use App\Filament\Resources\MediaResource\Widgets\MediaStorageChart;
use App\Filament\Resources\MediaResource\Widgets\MediaUploadWidget;
use App\Models\Media;
use App\Services\Media\MediaService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class MediaResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Media::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Media library';

    protected static ?string $modelLabel = 'media file';

    protected static ?string $pluralModelLabel = 'media library';

    protected static ?int $navigationSort = 93;

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->can('media.view')
            || $user->can('media.upload')
            || $user->can('media.delete')
            || $user->can('media.view_private');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ViewColumn::make('thumbnail')
                        ->view('filament.resources.media-thumbnail'),
                    Tables\Columns\TextColumn::make('name')
                        ->weight('bold')
                        ->limit(34)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('collection_name')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => MediaCategory::tryFrom($state)?->label() ?? $state),
                    Tables\Columns\IconColumn::make('is_private')
                        ->state(fn (Media $record): bool => $record->isPrivate())
                        ->icon(fn (bool $state): string => $state ? 'heroicon-o-lock-closed' : 'heroicon-o-globe-alt')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                        ->tooltip(fn (bool $state): string => $state ? 'Private — signed access only' : 'Public'),
                    Tables\Columns\TextColumn::make('size')
                        ->formatStateUsing(fn ($state): string => Number::fileSize((int) $state))
                        ->color('gray'),
                ])->space(2),
            ])
            ->contentGrid([
                'sm' => 1,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('collection_name')
                    ->label('Collection')
                    ->options(MediaCategory::options())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(collect(MediaType::cases())->mapWithKeys(
                        fn (MediaType $type): array => [$type->value => $type->label()],
                    )->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->ofType(MediaType::from($data['value']))
                        : $query),
                Tables\Filters\SelectFilter::make('disk')
                    ->options(fn (): array => Media::query()
                        ->distinct()
                        ->orderBy('disk')
                        ->pluck('disk', 'disk')
                        ->all()),
                Tables\Filters\SelectFilter::make('uploaded_by')
                    ->label('Uploaded by')
                    ->relationship('uploader', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Media $record): ?string => $record->isPrivate()
                        ? app(MediaService::class)->temporaryUrl($record)
                        : $record->publicUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (Media $record): bool => $record->isPrivate()
                        ? Auth::user()?->can('media.view_private')
                        : true),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['uploader']);
    }

    /**
     * @return array<class-string>
     */
    public static function getHeaderWidgets(): array
    {
        return [
            MediaUploadWidget::class,
            MediaStorageChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMedia::route('/'),
        ];
    }
}
