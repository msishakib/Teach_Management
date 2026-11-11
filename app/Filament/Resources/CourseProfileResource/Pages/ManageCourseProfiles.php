<?php

namespace App\Filament\Resources\CourseProfileResource\Pages;

use App\Filament\Resources\CourseProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCourseProfiles extends ManageRecords
{
    protected static string $resource = CourseProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}