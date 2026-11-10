<?php

namespace App\Filament\Resources\ReceiveVoucherResource\Pages;

use App\Filament\Resources\ReceiveVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReceiveVouchers extends ListRecords
{
    protected static string $resource = ReceiveVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
