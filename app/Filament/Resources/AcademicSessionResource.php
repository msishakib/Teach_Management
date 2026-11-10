<?php

namespace App\Filament\Resources;

use App\Enums\AcademicSessionStatus;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\AcademicSessionResource\Pages;
use App\Models\AcademicSession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AcademicSessionResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = AcademicSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Academic session';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('end_date')
                    ->required()
                    ->native(false)
                    ->afterOrEqual('start_date'),
                Forms\Components\Select::make('status')
                    ->options(AcademicSessionStatus::class)
                    ->required()
                    ->native(false),
                Forms\Components\Toggle::make('is_current')
                    ->label('Current session')
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_current')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AcademicSessionStatus::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('batches');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicSessions::route('/'),
            'create' => Pages\CreateAcademicSession::route('/create'),
            'edit' => Pages\EditAcademicSession::route('/{record}/edit'),
        ];
    }
}
