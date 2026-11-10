<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\UserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $roleIds = $data['roles'] ?? [];
        unset($data['roles']);

        return app(UserService::class)->create(
            $data,
            is_array($roleIds) ? $roleIds : [],
        );
    }
}
