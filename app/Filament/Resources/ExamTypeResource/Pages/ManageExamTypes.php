<?php

namespace App\Filament\Resources\ExamTypeResource\Pages;

use App\Filament\Resources\ExamTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageExamTypes extends ManageRecords
{
    protected static string $resource = ExamTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
