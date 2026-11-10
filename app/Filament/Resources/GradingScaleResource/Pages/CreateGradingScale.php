<?php

namespace App\Filament\Resources\GradingScaleResource\Pages;

use App\Filament\Resources\GradingScaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGradingScale extends CreateRecord
{
    protected static string $resource = GradingScaleResource::class;

    protected function afterCreate(): void
    {
        GradingScaleResource::ensureSingleDefault(
            $this->record,
            (bool) $this->record->is_default,
        );
    }
}
