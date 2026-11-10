<?php

namespace App\Filament\Resources\JournalVoucherResource\Pages;

use App\Filament\Resources\JournalVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJournalVouchers extends ListRecords
{
    protected static string $resource = JournalVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
