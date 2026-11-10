<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\ReceiveVoucherStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\ReceiveVoucherResource\Pages;
use App\Models\Account;
use App\Models\ReceiveVoucher;
use App\Models\Student;
use App\Services\ReceiveVoucherService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReceiveVoucherResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = ReceiveVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Receive vouchers';

    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->native(false)
                    ->default(now()),
                Forms\Components\Select::make('payer_student_id')
                    ->label('Payer (student)')
                    ->options(fn () => Student::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->visibleOn('create'),
                Forms\Components\Select::make('account_id')
                    ->label('Credit account')
                    ->options(fn () => Account::query()
                        ->where('is_group', false)
                        ->where('is_cash_or_bank', false)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $account) => [$account->id => $account->label()]))
                    ->searchable()
                    ->required()
                    ->disabled(fn (?ReceiveVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\Select::make('received_to_account_id')
                    ->label('Received to')
                    ->options(fn () => Account::query()
                        ->where('is_cash_or_bank', true)
                        ->where('is_group', false)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $account) => [$account->id => $account->label()]))
                    ->searchable()
                    ->required()
                    ->disabled(fn (?ReceiveVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix(fn () => (string) setting('general.currency_symbol', '৳'))
                    ->disabled(fn (?ReceiveVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\Select::make('method')
                    ->options(PaymentMethod::class)
                    ->required()
                    ->native(false)
                    ->disabled(fn (?ReceiveVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\FileUpload::make('attachment')
                    ->directory('receive-vouchers')
                    ->visibility('private')
                    ->nullable()
                    ->disabled(fn (?ReceiveVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\Textarea::make('narration')
                    ->rows(3)
                    ->columnSpanFull()
                    ->disabled(fn (?ReceiveVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\TextInput::make('voucher_no')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                Forms\Components\Select::make('status')
                    ->options(ReceiveVoucherStatus::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('voucher_no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payer.name')
                    ->label('Payer')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creditAccount.name')
                    ->label('Credit')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('receivedToAccount.name')
                    ->label('Received to')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn () => 'BDT')
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ReceiveVoucherStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (ReceiveVoucher $record) => $record->isDraft()),
                Tables\Actions\Action::make('approve')
                    ->label('Approve & post')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ReceiveVoucher $record) => $record->isDraft()
                        && Auth::user()?->can('voucher.approve'))
                    ->action(function (ReceiveVoucher $record): void {
                        app(ReceiveVoucherService::class)->approve(Auth::user(), $record);

                        Notification::make()
                            ->title('Receive voucher approved and posted')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-printer')
                    ->url(fn (ReceiveVoucher $record) => route('receive-vouchers.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['payer', 'creditAccount', 'receivedToAccount']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceiveVouchers::route('/'),
            'create' => Pages\CreateReceiveVoucher::route('/create'),
            'view' => Pages\ViewReceiveVoucher::route('/{record}'),
            'edit' => Pages\EditReceiveVoucher::route('/{record}/edit'),
        ];
    }
}
