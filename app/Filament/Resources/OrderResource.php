<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Services\Payments\CourseOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Course orders';

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('order_no')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('gateway')->disabled(),
            Forms\Components\TextInput::make('net_amount')->disabled(),
            Forms\Components\TextInput::make('gateway_txn_id')->disabled(),
            Forms\Components\TextInput::make('manual_trx_id')->disabled(),
            Forms\Components\Textarea::make('refund_reason')->disabled()->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('order_no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('student.name')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('course.name')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('net_amount')->money(fn () => 'BDT')->sortable(),
                Tables\Columns\TextColumn::make('gateway')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('gateway_txn_id')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('manual_trx_id')->label('Manual TrxID')->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(OrderStatus::class),
                Tables\Filters\SelectFilter::make('gateway')->options(PaymentGateway::class),
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('failed_only')
                    ->label('Failed payments')
                    ->query(fn (Builder $query) => $query->where('status', OrderStatus::Failed->value)),
                Tables\Filters\Filter::make('manual_pending')
                    ->label('Manual awaiting verification')
                    ->query(fn (Builder $query) => $query
                        ->where('gateway', PaymentGateway::Manual->value)
                        ->where('status', OrderStatus::Pending->value)
                        ->whereNotNull('manual_trx_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('verify_manual')
                    ->label('Verify payment')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->gateway === PaymentGateway::Manual
                        && $record->status === OrderStatus::Pending
                        && filled($record->manual_trx_id)
                        && Auth::user()?->can('payment.update'))
                    ->form([
                        Forms\Components\TextInput::make('trx_id')
                            ->label('TrxID')
                            ->required()
                            ->default(fn (Order $record) => $record->manual_trx_id),
                        Forms\Components\Select::make('method')
                            ->options(['bkash' => 'bKash', 'nagad' => 'Nagad'])
                            ->default(fn (Order $record) => $record->manual_method ?? 'bkash')
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(CourseOrderService::class)->verifyManual(
                            $record,
                            (string) $data['trx_id'],
                            Auth::user(),
                            (string) $data['method'],
                        );

                        Notification::make()->title('Manual payment verified')->success()->send();
                    }),
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->status === OrderStatus::Paid
                        && Auth::user()?->can('payment.refund'))
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(CourseOrderService::class)->refund(
                            $record,
                            (string) $data['reason'],
                            Auth::user(),
                        );

                        Notification::make()->title('Order refunded')->success()->send();
                    }),
                Tables\Actions\Action::make('receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record) => $record->receipt_id
                        ? route('receipts.pdf', $record->receipt_id)
                        : null)
                    ->visible(fn (Order $record) => filled($record->receipt_id))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentAttemptsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student', 'course', 'receipt']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
