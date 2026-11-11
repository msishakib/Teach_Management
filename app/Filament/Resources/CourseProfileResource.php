<?php

namespace App\Filament\Resources;

use App\Enums\FeeFrequency;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\CourseProfileResource\Pages;
use App\Models\CourseProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Public-website presentation (category, price, cover…) for an existing course.
 */
class CourseProfileResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = CourseProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Course showcase';

    protected static ?string $modelLabel = 'course showcase';

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('category')
                    ->maxLength(60)
                    ->datalist(fn () => CourseProfile::query()->whereNotNull('category')->distinct()->pluck('category')->all())
                    ->helperText('Used for the filter pills on the homepage.'),
                Forms\Components\TextInput::make('tagline')
                    ->maxLength(200)
                    ->helperText('Short card text. Falls back to the course description.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('duration')
                    ->placeholder('6 months')
                    ->maxLength(60),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave empty to use the course fee structure.'),
                Forms\Components\Select::make('price_period')
                    ->options(FeeFrequency::class)
                    ->default(FeeFrequency::Monthly->value)
                    ->required()
                    ->native(false),
                Forms\Components\FileUpload::make('image')
                    ->label('Cover image')
                    ->helperText('16:10 works best (e.g. 640×400). JPG/PNG is converted to WebP automatically.')
                    ->image()
                    ->disk('public')
                    ->directory('website/courses')
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(4096),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Popular badge')
                    ->inline(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\ImageColumn::make('image')->disk('public')->width(64)->height(40),
                Tables\Columns\TextColumn::make('course.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->badge()->sortable(),
                Tables\Columns\TextColumn::make('duration'),
                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : '৳ '.number_format((float) $state))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Popular'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('course');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCourseProfiles::route('/'),
        ];
    }
}
