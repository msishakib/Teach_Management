<?php

namespace App\Filament\Resources\PaymentVoucherResource\Pages;

use App\Filament\Resources\PaymentVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentVoucher extends EditRecord
{
    protected static string $resource = PaymentVoucherResource::class;

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
