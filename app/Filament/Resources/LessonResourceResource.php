<?php

namespace App\Filament\Resources;

use App\Enums\MediaCategory;
use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\LessonResourceResource\Pages;
use App\Models\Lesson;
use App\Models\LessonResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Downloadable attachments for a lesson. Files are uploaded through the Media
 * library and then linked here, so access always goes through the LMS policy.
 */
class LessonResourceResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = LessonResource::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'lesson resource';

    protected static ?string $pluralModelLabel = 'lesson resources';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('lesson_id')
                    ->relationship('lesson', 'title', fn (Builder $query) => $query->with('course')->orderBy('title'))
                    ->getOptionLabelFromRecordUsing(fn (Lesson $record): string => $record->course?->name
                        ? $record->course->name.' · '.$record->title
                        : $record->title)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('media_id')
                    ->label('File')
                    ->options(function (?LessonResource $record): array {
                        if (! $record instanceof LessonResource) {
                            return [];
                        }

                        return $record->getMedia(MediaCategory::LessonResource->value)
                            ->mapWithKeys(fn ($media): array => [(int) $media->getKey() => $media->displayName()])
                            ->all();
                    })
                    ->helperText('Optional while creating: save once, upload the file in the Media library against this resource, then select it here.')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('lesson.course.name')
                    ->label('Course')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lesson.title')
                    ->label('Lesson')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fileMedia.name')
                    ->label('File')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lesson_id')
                    ->label('Lesson')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->preload(),
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
        return parent::getEloquentQuery()->with(['lesson.course', 'fileMedia']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLessonResources::route('/'),
            'create' => Pages\CreateLessonResource::route('/create'),
            'edit' => Pages\EditLessonResource::route('/{record}/edit'),
        ];
    }
}
