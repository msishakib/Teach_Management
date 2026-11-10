<?php

namespace App\Filament\Resources\GradingScaleResource\Pages;

use App\Filament\Resources\GradingScaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGradingScale extends EditRecord
{
    protected static string $resource = GradingScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        GradingScaleResource::ensureSingleDefault(
            $this->record,
            (bool) $this->record->is_default,
        );
    }
}
