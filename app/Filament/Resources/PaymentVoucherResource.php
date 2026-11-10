<?php

namespace App\Filament\Resources;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentVoucherStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\PaymentVoucherResource\Pages;
use App\Models\Account;
use App\Models\PaymentVoucher;
use App\Models\Teacher;
use App\Services\PaymentVoucherService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PaymentVoucherResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = PaymentVoucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payment vouchers';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->native(false)
                    ->default(now()),
                Forms\Components\Select::make('payee_teacher_id')
                    ->label('Payee (teacher)')
                    ->options(fn () => Teacher::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->visibleOn('create'),
                Forms\Components\Select::make('account_id')
                    ->label('Expense account')
                    ->options(fn () => Account::query()
                        ->where('type', AccountType::Expense)
                        ->where('is_group', false)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"]))
                    ->searchable()
                    ->required()
                    ->disabled(fn (?PaymentVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\Select::make('paid_from_account_id')
                    ->label('Paid from')
                    ->options(fn () => Account::query()
                        ->where('is_cash_or_bank', true)
                        ->where('is_group', false)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"]))
                    ->searchable()
                    ->required()
                    ->disabled(fn (?PaymentVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix(fn () => (string) setting('general.currency_symbol', '৳'))
                    ->disabled(fn (?PaymentVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\Select::make('method')
                    ->options(PaymentMethod::class)
                    ->required()
                    ->native(false)
                    ->disabled(fn (?PaymentVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\FileUpload::make('attachment')
                    ->directory('payment-vouchers')
                    ->visibility('private')
                    ->nullable()
                    ->disabled(fn (?PaymentVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\Textarea::make('narration')
                    ->rows(3)
                    ->columnSpanFull()
                    ->disabled(fn (?PaymentVoucher $record) => $record && ! $record->isDraft()),
                Forms\Components\TextInput::make('voucher_no')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                Forms\Components\Select::make('status')
                    ->options(PaymentVoucherStatus::class)
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
                Tables\Columns\TextColumn::make('payee.name')
                    ->label('Payee')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expenseAccount.name')
                    ->label('Expense')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paidFromAccount.name')
                    ->label('Paid from')
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
                    ->options(PaymentVoucherStatus::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PaymentVoucher $record) => $record->isDraft()),
                Tables\Actions\Action::make('approve')
                    ->label('Approve & post')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentVoucher $record) => $record->isDraft()
                        && Auth::user()?->can('payment.approve'))
                    ->action(function (PaymentVoucher $record): void {
                        app(PaymentVoucherService::class)->approve(Auth::user(), $record);

                        Notification::make()
                            ->title('Payment voucher approved and posted')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-printer')
                    ->url(fn (PaymentVoucher $record) => route('payment-vouchers.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['payee', 'expenseAccount', 'paidFromAccount']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentVouchers::route('/'),
            'create' => Pages\CreatePaymentVoucher::route('/create'),
            'view' => Pages\ViewPaymentVoucher::route('/{record}'),
            'edit' => Pages\EditPaymentVoucher::route('/{record}/edit'),
        ];
    }
}
