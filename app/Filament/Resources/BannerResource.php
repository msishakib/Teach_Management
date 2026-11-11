<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BannerResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Homepage banners';

    protected static ?string $modelLabel = 'banner';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('English')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Headline')
                            ->required()
                            ->maxLength(150)
                            ->helperText('The last ~40% of the words are shown with the gradient.'),
                        Forms\Components\Textarea::make('subtitle')
                            ->rows(2)
                            ->maxLength(300),
                        Forms\Components\TextInput::make('cta_label')
                            ->label('Button label')
                            ->maxLength(60),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('বাংলা (optional)')
                    ->schema([
                        Forms\Components\TextInput::make('title_bn')
                            ->label('Headline (Bangla)')
                            ->maxLength(150),
                        Forms\Components\Textarea::make('subtitle_bn')
                            ->label('Subtitle (Bangla)')
                            ->rows(2)
                            ->maxLength(300),
                        Forms\Components\TextInput::make('cta_label_bn')
                            ->label('Button label (Bangla)')
                            ->maxLength(60),
                    ])
                    ->columns(1)
                    ->collapsed(),
                Forms\Components\TextInput::make('cta_url')
                    ->label('Button link')
                    ->helperText('A path like /apply, an anchor like #courses, or a full https:// URL. Defaults to the apply page.')
                    ->maxLength(255)
                    ->rules(['nullable', 'regex:/^(\/|#|https?:\/\/)/i']),
                Forms\Components\FileUpload::make('image')
                    ->label('Hero image')
                    ->helperText('Portrait 4:5 works best (e.g. 800×1000). JPG/PNG is converted to WebP automatically.')
                    ->image()
                    ->disk('public')
                    ->directory('website/banners')
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(4096),
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
                Tables\Columns\ImageColumn::make('image')->disk('public')->square()->width(48)->height(60),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(60),
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
            'index' => Pages\ManageBanners::route('/'),
        ];
    }
}
