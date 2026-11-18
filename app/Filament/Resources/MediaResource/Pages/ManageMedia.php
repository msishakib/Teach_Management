<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMedia extends ManageRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return MediaResource::getHeaderWidgets();
    }
}
