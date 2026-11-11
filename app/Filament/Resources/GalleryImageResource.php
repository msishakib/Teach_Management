<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\GalleryImageResource\Pages;
use App\Models\GalleryImage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GalleryImageResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = GalleryImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Gallery';

    protected static ?string $modelLabel = 'gallery image';

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Caption / alt text')
                    ->required()
                    ->maxLength(160)
                    ->helperText('Shown under the photo and used as its alt text.'),
                Forms\Components\TextInput::make('title_bn')
                    ->label('Caption (Bangla, optional)')
                    ->maxLength(160),
                Forms\Components\FileUpload::make('image')
                    ->required()
                    ->image()
                    ->disk('public')
                    ->directory('website/gallery')
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(6144)
                    ->helperText('JPG/PNG is converted to WebP automatically.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0),
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
                Tables\Columns\ImageColumn::make('image')->disk('public')->width(72)->height(54),
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageGalleryImages::route('/'),
        ];
    }
}
