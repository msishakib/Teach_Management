<?php

namespace App\Filament\Resources;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use App\Services\MenuService;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = MenuItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Menu';

    protected static ?string $modelLabel = 'menu item';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('location')
                    ->options(MenuLocation::class)
                    ->default(MenuLocation::Header->value)
                    ->required(),
                Forms\Components\Select::make('link_type')
                    ->label('Link to')
                    ->options(MenuLinkType::class)
                    ->default(MenuLinkType::Section->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('target', null)),
                Forms\Components\Select::make('target')
                    ->label(fn (Forms\Get $get) => static::linkType($get) === MenuLinkType::Page ? 'Page' : 'Section')
                    ->options(fn (Forms\Get $get) => static::linkType($get)?->targets() ?? [])
                    ->visible(fn (Forms\Get $get) => static::linkType($get) !== MenuLinkType::Custom)
                    ->required(fn (Forms\Get $get) => static::linkType($get) !== MenuLinkType::Custom)
                    ->in(fn (Forms\Get $get) => array_keys(static::linkType($get)?->targets() ?? [])),
                Forms\Components\TextInput::make('target')
                    ->label('URL')
                    ->placeholder('https://example.com or /storage/prospectus.pdf')
                    ->helperText('Full https:// link, a site path starting with /, or an #anchor.')
                    ->maxLength(500)
                    ->visible(fn (Forms\Get $get) => static::linkType($get) === MenuLinkType::Custom)
                    ->required(fn (Forms\Get $get) => static::linkType($get) === MenuLinkType::Custom)
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! MenuService::isSafeCustomUrl(is_string($value) ? $value : null)) {
                            $fail('Enter an https:// URL, a path starting with /, or an #anchor.');
                        }
                    }),
                Forms\Components\TextInput::make('label')
                    ->label('Label (English)')
                    ->required()
                    ->maxLength(60),
                Forms\Components\TextInput::make('label_bn')
                    ->label('Label (Bangla, optional)')
                    ->maxLength(60),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(fn () => (int) MenuItem::query()->max('sort_order') + 10),
                Forms\Components\Toggle::make('open_in_new_tab')
                    ->inline(false),
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
                Tables\Columns\TextColumn::make('label')->searchable()->description(fn (MenuItem $record) => $record->label_bn),
                Tables\Columns\TextColumn::make('link_type')->label('Link to')->badge(),
                Tables\Columns\TextColumn::make('target')
                    ->formatStateUsing(fn (MenuItem $record, string $state) => $record->link_type->targets()[$state] ?? $state)
                    ->limit(40),
                Tables\Columns\IconColumn::make('open_in_new_tab')->label('New tab')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->defaultGroup(Tables\Grouping\Group::make('location')->titlePrefixedWithLabel(false))
            ->filters([
                Tables\Filters\SelectFilter::make('location')->options(MenuLocation::class),
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
            'index' => Pages\ManageMenuItems::route('/'),
        ];
    }

    protected static function linkType(Forms\Get $get): ?MenuLinkType
    {
        $type = $get('link_type');

        return $type instanceof MenuLinkType ? $type : MenuLinkType::tryFrom((string) $type);
    }
}
