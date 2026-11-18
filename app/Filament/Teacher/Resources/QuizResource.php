<?php

namespace App\Filament\Teacher\Resources;

use App\Filament\Resources\QuizResource as AdminQuizResource;
use App\Filament\Resources\QuizResource\RelationManagers\AttemptsRelationManager;
use App\Filament\Resources\QuizResource\RelationManagers\QuestionsRelationManager;
use App\Filament\Teacher\Resources\QuizResource\Pages;

class QuizResource extends AdminQuizResource
{
    protected static ?string $navigationGroup = 'Teaching';

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
            AttemptsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizzes::route('/'),
            'create' => Pages\CreateQuiz::route('/create'),
            'edit' => Pages\EditQuiz::route('/{record}/edit'),
        ];
    }
}
