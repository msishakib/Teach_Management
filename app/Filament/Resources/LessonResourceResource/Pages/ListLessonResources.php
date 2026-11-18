<?php

namespace App\Filament\Resources\LessonResourceResource\Pages;

use App\Filament\Resources\LessonResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLessonResources extends ListRecords
{
    protected static string $resource = LessonResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
