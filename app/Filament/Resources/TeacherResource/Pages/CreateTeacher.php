<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use App\Services\TeacherService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $createUser = (bool) ($this->data['create_user'] ?? false);

        $userAttributes = null;

        if ($createUser) {
            $userAttributes = [
                'email' => $data['user_email'] ?? null,
                'password' => $data['user_password'] ?? null,
                'name' => $data['name'] ?? null,
            ];
        }

        unset($data['user_email'], $data['user_password'], $data['create_user']);

        return app(TeacherService::class)->create($data, $userAttributes);
    }
}
