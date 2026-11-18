<?php

namespace App\Filament\Resources\LessonResourceResource\Pages;

use App\Filament\Resources\LessonResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLessonResource extends EditRecord
{
    protected static string $resource = LessonResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
