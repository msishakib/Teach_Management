<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\TeacherProfileResource\Pages;
use App\Models\TeacherProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Public-website presentation (photo, subject, bio) for an existing teacher.
 */
class TeacherProfileResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = TeacherProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Featured teachers';

    protected static ?string $modelLabel = 'featured teacher';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('teacher_id')
                    ->label('Teacher')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('subject')
                    ->maxLength(120)
                    ->placeholder('Mathematics'),
                Forms\Components\TextInput::make('qualification')
                    ->maxLength(255)
                    ->placeholder('M.Sc. in Mathematics, University of Dhaka')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('bio')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('photo')
                    ->helperText('Portrait 4:5 works best (e.g. 480×600). JPG/PNG is converted to WebP automatically.')
                    ->image()
                    ->disk('public')
                    ->directory('website/teachers')
                    ->visibility('public')
                    ->imageEditor()
                    ->maxSize(4096),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Show on homepage')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\ImageColumn::make('photo')->disk('public')->circular(),
                Tables\Columns\TextColumn::make('teacher.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject')->badge(),
                Tables\Columns\ToggleColumn::make('is_featured')->label('On homepage'),
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
        return parent::getEloquentQuery()->with('teacher');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTeacherProfiles::route('/'),
        ];
    }
}
