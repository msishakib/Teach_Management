<?php

namespace App\Filament\Resources;

use App\Enums\DeliveryMode;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\CourseResource\Pages;
use App\Models\Course;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CourseResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Course details')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('short_description')
                            ->label('Short description')
                            ->helperText('Used on the public course page and search results.')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('delivery_mode')
                            ->options(DeliveryMode::options())
                            ->default(DeliveryMode::Offline->value)
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Toggle::make('is_published')
                            ->label('Published online')
                            ->helperText('Published courses have a public sales page and learner player.')
                            ->inline(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Online pricing & access')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (): string => (string) (app(\App\Services\SiteProfileService::class)->profile()['currency'] ?? '')),
                        Forms\Components\TextInput::make('sale_price')
                            ->label('Sale price')
                            ->numeric()
                            ->minValue(0)
                            ->lte('price')
                            ->helperText('Optional. Leave empty for the regular price.'),
                        Forms\Components\TextInput::make('access_days')
                            ->label('Access duration (days)')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->helperText('Leave empty for lifetime access.'),
                        Forms\Components\TextInput::make('level')
                            ->maxLength(60)
                            ->placeholder('Beginner'),
                        Forms\Components\TextInput::make('language')
                            ->maxLength(60)
                            ->placeholder('Bangla / English'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Learning outcomes')
                    ->schema([
                        Forms\Components\TagsInput::make('what_you_learn')
                            ->label('What you will learn')
                            ->placeholder('Add an outcome and press Enter')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('requirements')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
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
                Tables\Columns\TextColumn::make('delivery_mode')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 2))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Sections')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lessons_count')
                    ->counts('lessons')
                    ->label('Lessons')
                    ->sortable(),
                Tables\Columns\TextColumn::make('batches_count')
                    ->counts('batches')
                    ->label('Batches')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('delivery_mode')
                    ->options(DeliveryMode::options()),
                Tables\Filters\TernaryFilter::make('is_published'),
                Tables\Filters\TernaryFilter::make('is_active'),
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
        return parent::getEloquentQuery()->withCount(['batches', 'sections', 'lessons']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
