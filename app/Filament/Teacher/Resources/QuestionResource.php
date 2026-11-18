<?php

namespace App\Filament\Teacher\Resources;

use App\Filament\Resources\QuestionResource as AdminQuestionResource;
use App\Filament\Teacher\Resources\QuestionResource\Pages;

class QuestionResource extends AdminQuestionResource
{
    protected static ?string $navigationGroup = 'Teaching';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
