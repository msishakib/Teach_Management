<?php

namespace App\Filament\Resources\AssignmentResource\RelationManagers;

use App\Models\AssignmentSubmission;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Services\Lms\AssignmentSubmissionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Submissions';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('marks')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\Textarea::make('feedback')->rows(4),
            Forms\Components\Toggle::make('return_to_student')
                ->label('Mark as returned')
                ->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScope(PortalStudentOwnedScope::class)
                ->with(['student', 'media']))
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\IconColumn::make('is_late')->boolean()->label('Late'),
                Tables\Columns\TextColumn::make('marks'),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
                Tables\Columns\TextColumn::make('graded_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('files')
                    ->label('Files')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (AssignmentSubmission $record): string => '#')
                    ->modalHeading('Submission files')
                    ->modalContent(fn (AssignmentSubmission $record) => view('filament.assignments.submission-files', [
                        'submission' => $record,
                    ]))
                    ->modalSubmitAction(false),
                Tables\Actions\Action::make('grade')
                    ->icon('heroicon-o-academic-cap')
                    ->form([
                        Forms\Components\TextInput::make('marks')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\Textarea::make('feedback')->rows(4),
                        Forms\Components\Toggle::make('return_to_student')
                            ->label('Return to student')
                            ->inline(false),
                    ])
                    ->fillForm(fn (AssignmentSubmission $record): array => [
                        'marks' => $record->marks,
                        'feedback' => $record->feedback,
                    ])
                    ->action(function (AssignmentSubmission $record, array $data, AssignmentSubmissionService $service): void {
                        $service->grade(
                            $record,
                            Auth::user(),
                            (float) $data['marks'],
                            $data['feedback'] ?? null,
                            (bool) ($data['return_to_student'] ?? false),
                        );
                    }),
            ]);
    }
}
