<?php

namespace App\Filament\Resources\QuizResource\RelationManagers;

use App\Models\QuizAnswer;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Services\Lms\QuizGradingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'attempts';

    protected static ?string $title = 'Attempts';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->with(['student', 'answers.question']))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')->searchable(),
                Tables\Columns\TextColumn::make('attempt_no'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('score'),
                Tables\Columns\TextColumn::make('percent'),
                Tables\Columns\TextColumn::make('started_at')->dateTime(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('gradeShortAnswers')
                    ->label('Grade short answers')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn ($record) => $record->answers->contains(fn (QuizAnswer $a) => $a->question?->type?->value === 'short_answer'))
                    ->form(function ($record): array {
                        return $record->answers
                            ->filter(fn (QuizAnswer $a) => $a->question?->type?->value === 'short_answer')
                            ->map(fn (QuizAnswer $a) => Forms\Components\TextInput::make('marks_'.$a->id)
                                ->label(\Illuminate\Support\Str::limit($a->question->text, 60).' — student: '.($a->answer_text ?: '—'))
                                ->numeric()
                                ->minValue(0)
                                ->default($a->marks_awarded))
                            ->values()
                            ->all();
                    })
                    ->action(function ($record, array $data, QuizGradingService $grading): void {
                        foreach ($data as $key => $marks) {
                            if (! str_starts_with($key, 'marks_') || $marks === null || $marks === '') {
                                continue;
                            }

                            $answerId = (int) str_replace('marks_', '', $key);
                            $answer = $record->answers->firstWhere('id', $answerId);

                            if ($answer) {
                                $grading->awardManualMarks($answer, (float) $marks);
                            }
                        }
                    }),
            ]);
    }
}
