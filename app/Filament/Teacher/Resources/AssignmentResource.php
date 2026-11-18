<?php

namespace App\Filament\Teacher\Resources;

use App\Filament\Resources\AssignmentResource as AdminAssignmentResource;
use App\Filament\Resources\AssignmentResource\RelationManagers\SubmissionsRelationManager;
use App\Filament\Teacher\Resources\AssignmentResource\Pages;

class AssignmentResource extends AdminAssignmentResource
{
    protected static ?string $navigationGroup = 'Teaching';

    public static function getRelations(): array
    {
        return [
            SubmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignments::route('/'),
            'create' => Pages\CreateAssignment::route('/create'),
            'edit' => Pages\EditAssignment::route('/{record}/edit'),
        ];
    }
}
