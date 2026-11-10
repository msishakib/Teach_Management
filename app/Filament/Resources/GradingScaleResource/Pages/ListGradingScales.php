<?php

namespace App\Filament\Resources\GradingScaleResource\Pages;

use App\Filament\Resources\GradingScaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGradingScales extends ListRecords
{
    protected static string $resource = GradingScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
