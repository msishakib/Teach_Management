<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use App\Filament\Resources\CourseSectionResource;
use App\Models\Course;
use App\Models\CourseSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Curriculum sections';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('lessons'))
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
                Tables\Columns\TextColumn::make('lessons_count')
                    ->counts('lessons')
                    ->label('Lessons'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var Course $course */
                        $course = $this->getOwnerRecord();
                        $data['course_id'] = $course->id;
                        $data['sort_order'] ??= (int) CourseSection::query()
                            ->where('course_id', $course->id)
                            ->max('sort_order') + 1;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('lessons')
                    ->label('Manage lessons')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn (CourseSection $record): string => CourseSectionResource::getUrl('edit', ['record' => $record])),
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
