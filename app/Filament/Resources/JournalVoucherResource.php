<?php

namespace App\Filament\Resources;

use App\Enums\JournalEntryStatus;
use App\Enums\VoucherType;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\JournalVoucherResource\Pages;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class JournalVoucherResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = JournalEntry::class;

    protected static ?string $slug = 'journal-vouchers';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Journal vouchers';

    protected static ?string $modelLabel = 'journal voucher';

    protected static ?string $pluralModelLabel = 'journal vouchers';

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('entry_date')
                    ->label('Date')
                    ->required()
                    ->native(false)
                    ->default(now())
                    ->disabled(fn (?JournalEntry $record) => $record && ! $record->isDraft()),
                Forms\Components\TextInput::make('entry_no')
                    ->label('Voucher no')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['edit', 'view']),
                Forms\Components\Select::make('status')
                    ->options(JournalEntryStatus::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['edit', 'view']),
                Forms\Components\Textarea::make('narration')
                    ->rows(2)
                    ->columnSpanFull()
                    ->disabled(fn (?JournalEntry $record) => $record && ! $record->isDraft()),
                Forms\Components\Repeater::make('lines')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('Account')
                            ->options(fn () => Account::query()
                                ->where('is_group', false)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (Account $account) => [$account->id => $account->label()]))
                            ->searchable()
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('debit')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix(fn () => (string) setting('general.currency_symbol', '৳'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                                if ((float) $state > 0) {
                                    $set('credit', 0);
                                }
                            }),
                        Forms\Components\TextInput::make('credit')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix(fn () => (string) setting('general.currency_symbol', '৳'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                                if ((float) $state > 0) {
                                    $set('debit', 0);
                                }
                            }),
                        Forms\Components\TextInput::make('description')
                            ->maxLength(255)
                            ->columnSpan(2),
                    ])
                    ->columns(6)
                    ->defaultItems(2)
                    ->minItems(2)
                    ->addActionLabel('Add line')
                    ->columnSpanFull()
                    ->disabled(fn (?JournalEntry $record) => $record && ! $record->isDraft())
                    ->dehydrated()
                    ->live(),
                Forms\Components\Placeholder::make('totals')
                    ->label('Totals')
                    ->content(function (Get $get): string {
                        $lines = collect($get('lines') ?? []);
                        $debit = number_format((float) $lines->sum(fn ($line) => (float) ($line['debit'] ?? 0)), 2, '.', '');
                        $credit = number_format((float) $lines->sum(fn ($line) => (float) ($line['credit'] ?? 0)), 2, '.', '');
                        $symbol = (string) setting('general.currency_symbol', '৳');
                        $balanced = bccomp($debit, $credit, 2) === 0 ? 'Balanced' : 'Unbalanced';

                        return "Debit {$symbol}{$debit} · Credit {$symbol}{$credit} · {$balanced}";
                    })
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('entry_no')
                    ->label('Voucher no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('entry_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('narration')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('debit_total')
                    ->label('Debit')
                    ->state(fn (JournalEntry $record) => $record->lines->sum('debit'))
                    ->money(fn () => 'BDT'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created by')
                    ->toggleable(),
            ])
            ->defaultSort('entry_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(JournalEntryStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (JournalEntry $record) => $record->isDraft()),
                Tables\Actions\Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (JournalEntry $record) => $record->isDraft()
                        && Auth::user()?->can('journal.approve'))
                    ->action(function (JournalEntry $record): void {
                        try {
                            app(AccountingService::class)->post($record);

                            Notification::make()
                                ->title('Journal voucher posted')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Could not post voucher')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reverse')
                    ->label('Reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (JournalEntry $record) => $record->isPosted()
                        && Auth::user()?->can('journal.approve'))
                    ->action(function (JournalEntry $record): void {
                        app(AccountingService::class)->reverse($record, Auth::user());

                        Notification::make()
                            ->title('Journal voucher reversed')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('voucher_type', VoucherType::Journal)
            ->with(['lines', 'createdBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalVouchers::route('/'),
            'create' => Pages\CreateJournalVoucher::route('/create'),
            'view' => Pages\ViewJournalVoucher::route('/{record}'),
            'edit' => Pages\EditJournalVoucher::route('/{record}/edit'),
        ];
    }
}
