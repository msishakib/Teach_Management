<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\TeacherSalaryStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\TeacherSalaryResource\Pages;
use App\Models\Account;
use App\Models\TeacherSalary;
use App\Services\TeacherSalaryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TeacherSalaryResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = TeacherSalary::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Teacher salaries';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('teacher.name')
                    ->label('Teacher')
                    ->disabled(),
                Forms\Components\TextInput::make('month')
                    ->disabled(),
                Forms\Components\TextInput::make('amount')
                    ->label('Basic')
                    ->disabled(),
                Forms\Components\TextInput::make('bonus')
                    ->disabled(),
                Forms\Components\TextInput::make('deductions')
                    ->disabled(),
                Forms\Components\TextInput::make('net')
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->options(TeacherSalaryStatus::class)
                    ->disabled(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('teacher.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('month')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Basic')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('bonus')
                    ->numeric(2)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deductions')
                    ->numeric(2)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('net')
                    ->numeric(2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paymentVoucher.voucher_no')
                    ->label('Voucher')
                    ->placeholder('—')
                    ->url(fn (TeacherSalary $record) => $record->paymentVoucher
                        ? PaymentVoucherResource::getUrl('view', ['record' => $record->paymentVoucher])
                        : null),
            ])
            ->defaultSort('month', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(TeacherSalaryStatus::class),
                Tables\Filters\Filter::make('month')
                    ->form([
                        Forms\Components\TextInput::make('month')
                            ->placeholder('YYYY-MM'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['month'] ?? null,
                            fn (Builder $q, string $month) => $q->where('month', $month),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('pay')
                    ->label('Create payment voucher')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (TeacherSalary $record) => $record->canPay()
                        && Auth::user()?->can('payment.create'))
                    ->form([
                        Forms\Components\Select::make('paid_from_account_id')
                            ->label('Paid from')
                            ->options(fn () => Account::query()
                                ->where('is_cash_or_bank', true)
                                ->where('is_group', false)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (Account $account) => [$account->id => "{$account->code} — {$account->name}"]))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('method')
                            ->options(PaymentMethod::class)
                            ->default(PaymentMethod::Bank->value)
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('date')
                            ->default(now())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (TeacherSalary $record, array $data): void {
                        try {
                            $voucher = app(TeacherSalaryService::class)->pay(Auth::user(), $record, $data);

                            Notification::make()
                                ->title('Payment voucher created')
                                ->body($voucher->voucher_no)
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Could not create voucher')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['teacher', 'paymentVoucher']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherSalaries::route('/'),
            'view' => Pages\ViewTeacherSalary::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
