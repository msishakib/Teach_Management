<?php

namespace App\Filament\Resources;

use App\Enums\NoticeAudience;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\NoticeResource\Pages;
use App\Models\Notice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NoticeResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Notice::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),
                Forms\Components\Select::make('audience')
                    ->options(NoticeAudience::class)
                    ->required()
                    ->live()
                    ->native(false),
                Forms\Components\Select::make('batch_id')
                    ->relationship('batch', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Forms\Get $get) => $get('audience') === NoticeAudience::Batch->value
                        || $get('audience') === NoticeAudience::Batch),
                Forms\Components\DateTimePicker::make('published_at')
                    ->native(false)
                    ->default(now()),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('audience')->badge(),
                Tables\Columns\TextColumn::make('batch.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('audience')->options(NoticeAudience::class),
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
        return parent::getEloquentQuery()->with(['batch', 'createdBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageNotices::route('/'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
