<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Filament\Resources\CourseResource\RelationManagers\SectionsRelationManager;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            SectionsRelationManager::class,
        ];
    }
}
