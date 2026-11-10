<?php

namespace App\Filament\Resources;

use App\Enums\AccountKind;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Chart of accounts';

    protected static ?string $modelLabel = 'account';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('kind')
                    ->label('Account kind')
                    ->options(AccountKind::class)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if ($state === AccountKind::Group->value) {
                            $set('parent_id', null);
                            $set('is_cash_or_bank', false);
                            $set('opening_balance', 0);
                        }
                    })
                    ->dehydrated(false)
                    ->default(fn (?Account $record) => $record?->kind()->value ?? AccountKind::Ledger->value),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent')
                    ->options(function (Get $get, ?Account $record) {
                        $kind = AccountKind::tryFrom((string) $get('kind')) ?? AccountKind::Ledger;

                        return Account::query()
                            ->where('is_group', true)
                            ->when(
                                $kind === AccountKind::SubGroup,
                                fn (Builder $query) => $query->whereNull('parent_id'),
                            )
                            ->when(
                                $record?->exists,
                                fn (Builder $query) => $query->whereKeyNot($record->id),
                            )
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Account $account) => [$account->id => $account->label()]);
                    })
                    ->searchable()
                    ->nullable()
                    ->required(fn (Get $get) => in_array($get('kind'), [
                        AccountKind::SubGroup->value,
                        AccountKind::Ledger->value,
                    ], true))
                    ->visible(fn (Get $get) => $get('kind') !== AccountKind::Group->value)
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        if (! $state) {
                            return;
                        }

                        $parent = Account::query()->find($state);
                        if ($parent) {
                            $set('type', $parent->type->value);
                        }
                    }),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options(AccountType::class)
                    ->required()
                    ->native(false)
                    ->disabled(fn (Get $get) => filled($get('parent_id')))
                    ->dehydrated(),
                Forms\Components\Toggle::make('is_cash_or_bank')
                    ->label('Cash / bank account')
                    ->visible(fn (Get $get) => $get('kind') === AccountKind::Ledger->value)
                    ->default(false),
                Forms\Components\TextInput::make('opening_balance')
                    ->numeric()
                    ->default(0)
                    ->prefix(fn () => (string) setting('general.currency_symbol', '৳'))
                    ->visible(fn (Get $get) => $get('kind') === AccountKind::Ledger->value),
                Forms\Components\Select::make('status')
                    ->options(AccountStatus::class)
                    ->required()
                    ->native(false)
                    ->default(AccountStatus::Active->value),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->state(fn (Account $record) => $record->kind()->getLabel()),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_cash_or_bank')
                    ->label('Cash/Bank')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('opening_balance')
                    ->money(fn () => 'BDT')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(AccountType::class),
                Tables\Filters\SelectFilter::make('status')
                    ->options(AccountStatus::class),
                Tables\Filters\TernaryFilter::make('is_group')
                    ->label('Groups only'),
                Tables\Filters\TernaryFilter::make('is_cash_or_bank')
                    ->label('Cash/Bank'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Account $record): void {
                        if ($record->children()->exists() || $record->journalLines()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Account in use')
                                ->body('Accounts with children or journal lines cannot be deleted.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('parent');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
