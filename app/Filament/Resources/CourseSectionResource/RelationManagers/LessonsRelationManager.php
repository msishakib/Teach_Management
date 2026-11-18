<?php

namespace App\Filament\Resources\CourseSectionResource\RelationManagers;

use App\Enums\LessonType;
use App\Enums\LessonVideoType;
use App\Enums\MediaCategory;
use App\Filament\Resources\LessonResourceResource;
use App\Models\CourseSection;
use App\Models\Lesson;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'Lessons';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Select::make('type')
                    ->options(LessonType::options())
                    ->default(LessonType::Video->value)
                    ->required()
                    ->native(false)
                    ->live(),
                Forms\Components\TextInput::make('duration_seconds')
                    ->label('Duration (seconds)')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->helperText('e.g. 630 for 10:30.'),
                Forms\Components\Textarea::make('content')
                    ->label('Lesson notes / text content')
                    ->rows(6)
                    ->columnSpanFull(),
                Forms\Components\Select::make('video_type')
                    ->options(LessonVideoType::options())
                    ->default(LessonVideoType::Embed->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->visible(fn (Forms\Get $get): bool => $get('type') === LessonType::Video->value),
                Forms\Components\TextInput::make('video_url')
                    ->label('Video URL')
                    ->url()
                    ->maxLength(255)
                    ->placeholder('https://www.youtube.com/watch?v=…')
                    ->helperText('YouTube or Vimeo links are converted to privacy-friendly embeds.')
                    ->required(fn (Forms\Get $get): bool => $get('type') === LessonType::Video->value
                        && $get('video_type') === LessonVideoType::Embed->value)
                    ->visible(fn (Forms\Get $get): bool => $get('type') === LessonType::Video->value
                        && $get('video_type') === LessonVideoType::Embed->value)
                    ->columnSpanFull(),
                Forms\Components\Select::make('media_id')
                    ->label('Uploaded video')
                    ->options(function (?Lesson $record): array {
                        if (! $record instanceof Lesson) {
                            return [];
                        }

                        return $record->getMedia(MediaCategory::CourseVideo->value)
                            ->mapWithKeys(fn ($media): array => [(int) $media->getKey() => $media->displayName()])
                            ->all();
                    })
                    ->helperText('Upload mp4/webm files for this lesson in the Media library, then select the file here. Save the lesson once before uploading.')
                    ->visible(fn (Forms\Get $get): bool => $get('type') === LessonType::Video->value
                        && $get('video_type') === LessonVideoType::Upload->value)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('drip_after_days')
                    ->label('Unlock after (days)')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->helperText('Leave empty to unlock immediately.'),
                Forms\Components\Toggle::make('is_free_preview')
                    ->label('Free preview')
                    ->inline(false),
                Forms\Components\Toggle::make('is_published')
                    ->label('Published')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state): string => course_duration((int) $state))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_free_preview')
                    ->label('Free preview')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                Tables\Columns\TextColumn::make('drip_after_days')
                    ->label('Unlock after')
                    ->suffix(' days')
                    ->placeholder('Immediately')
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var CourseSection $section */
                        $section = $this->getOwnerRecord();
                        $data['course_id'] = $section->course_id;
                        $data['sort_order'] ??= (int) Lesson::query()
                            ->where('section_id', $section->id)
                            ->max('sort_order') + 1;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('resources')
                    ->label('Resources')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (Lesson $record): string => LessonResourceResource::getUrl('index', [
                        'tableFilters' => [
                            'lesson_id' => ['value' => $record->id],
                        ],
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
