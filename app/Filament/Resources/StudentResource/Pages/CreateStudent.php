<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Services\StudentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $guardians = $data['guardianItems'] ?? [];
        $createUser = (bool) ($this->data['create_user'] ?? false);

        $userAttributes = null;

        if ($createUser) {
            $userAttributes = [
                'email' => $data['user_email'] ?? null,
                'password' => $data['user_password'] ?? null,
                'name' => $data['name'] ?? null,
            ];
        }

        unset(
            $data['guardianItems'],
            $data['user_email'],
            $data['user_password'],
            $data['create_user'],
        );

        return app(StudentService::class)->create($data, $userAttributes, is_array($guardians) ? $guardians : []);
    }
}
