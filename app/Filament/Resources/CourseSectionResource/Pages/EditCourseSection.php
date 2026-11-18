<?php

namespace App\Filament\Resources\CourseSectionResource\Pages;

use App\Filament\Resources\CourseSectionResource;
use App\Filament\Resources\CourseSectionResource\RelationManagers\LessonsRelationManager;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourseSection extends EditRecord
{
    protected static string $resource = CourseSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            LessonsRelationManager::class,
        ];
    }
}
