<?php

namespace App\Filament\Resources\AdmissionApplicationResource\Pages;

use App\Filament\Resources\AdmissionApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdmissionApplications extends ListRecords
{
    protected static string $resource = AdmissionApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

