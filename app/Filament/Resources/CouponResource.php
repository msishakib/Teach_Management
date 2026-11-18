<?php

namespace App\Filament\Resources;

use App\Enums\CouponType;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Coupons';

    protected static ?int $navigationSort = 26;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(64)
                ->dehydrateStateUsing(fn (?string $state) => $state !== null ? strtoupper(trim($state)) : null),
            Forms\Components\Select::make('type')
                ->options(CouponType::class)
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('value')
                ->numeric()
                ->required()
                ->minValue(0.01),
            Forms\Components\TextInput::make('max_uses')
                ->numeric()
                ->nullable()
                ->minValue(1),
            Forms\Components\TextInput::make('used_count')
                ->numeric()
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),
            Forms\Components\DateTimePicker::make('valid_from')->native(false),
            Forms\Components\DateTimePicker::make('valid_until')->native(false),
            Forms\Components\Select::make('course_id')
                ->relationship('course', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('value'),
                Tables\Columns\TextColumn::make('used_count')->label('Used'),
                Tables\Columns\TextColumn::make('max_uses')->placeholder('∞'),
                Tables\Columns\TextColumn::make('course.name')->placeholder('All courses'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('valid_until')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('course');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
