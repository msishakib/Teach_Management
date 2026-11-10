<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Enums\AccountKind;
use App\Filament\Resources\AccountResource;
use App\Services\AccountService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['kind'] = $this->data['kind'] ?? AccountKind::Ledger->value;

        return app(AccountService::class)->create($data);
    }
}
