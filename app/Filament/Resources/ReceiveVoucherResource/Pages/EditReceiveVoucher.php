<?php

namespace App\Filament\Resources\ReceiveVoucherResource\Pages;

use App\Filament\Resources\ReceiveVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReceiveVoucher extends EditRecord
{
    protected static string $resource = ReceiveVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->record->isDraft(), 403);
    }
}
