<?php

namespace App\Filament\Resources\QuizResource\RelationManagers;

use App\Models\Question;
use App\Models\Quiz;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'quizQuestions';

    protected static ?string $title = 'Questions';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('question_id')
                ->label('Question')
                ->options(function (): array {
                    /** @var Quiz $quiz */
                    $quiz = $this->getOwnerRecord();

                    return Question::query()
                        ->where('course_id', $quiz->course_id)
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Question $q): array => [
                            $q->id => \Illuminate\Support\Str::limit($q->text, 80).' ('.$q->type->getLabel().')',
                        ])
                        ->all();
                })
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->integer()
                ->default(0),
            Forms\Components\TextInput::make('marks_override')
                ->numeric()
                ->minValue(0)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('question'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#'),
                Tables\Columns\TextColumn::make('question.text')->limit(70)->label('Question'),
                Tables\Columns\TextColumn::make('question.type')->badge(),
                Tables\Columns\TextColumn::make('marks_override')->label('Marks override'),
                Tables\Columns\TextColumn::make('question.marks')->label('Bank marks'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['sort_order'] ??= ((int) $this->getOwnerRecord()->quizQuestions()->max('sort_order')) + 1;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
