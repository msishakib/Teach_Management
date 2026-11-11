<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only inbox for contact messages and admission applications from the public site.
 */
class ContactMessageResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Inquiries';

    protected static ?string $modelLabel = 'inquiry';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::query()->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\TextEntry::make('type')->badge(),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\TextEntry::make('phone')->placeholder('—'),
                Infolists\Components\TextEntry::make('email')->placeholder('—'),
                Infolists\Components\TextEntry::make('course.name')->label('Course')->placeholder('—'),
                Infolists\Components\TextEntry::make('subject')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('message')->placeholder('—')->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === ContactMessage::TYPE_ADMISSION ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('name')->searchable()->weight(fn (ContactMessage $record) => $record->read_at ? null : 'bold'),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('course.name')->label('Course')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\IconColumn::make('read_at')->label('Read')->boolean()->getStateUsing(fn (ContactMessage $record) => $record->read_at !== null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    ContactMessage::TYPE_ADMISSION => 'Admission',
                    ContactMessage::TYPE_CONTACT => 'Contact',
                ]),
                Tables\Filters\TernaryFilter::make('read_at')
                    ->label('Read status')
                    ->nullable()
                    ->trueLabel('Read')
                    ->falseLabel('Unread'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('mark_read')
                    ->label('Mark read')
                    ->icon('heroicon-o-check')
                    ->visible(fn (ContactMessage $record) => $record->read_at === null)
                    ->action(fn (ContactMessage $record) => $record->forceFill(['read_at' => now()])->save()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_read')
                        ->label('Mark as read')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each(fn (ContactMessage $record) => $record->forceFill(['read_at' => $record->read_at ?? now()])->save()))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('course');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageContactMessages::route('/'),
        ];
    }
}
