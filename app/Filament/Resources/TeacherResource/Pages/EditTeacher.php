<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use App\Services\TeacherService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['user_email'] = $this->record->user?->email;
        $data['create_user'] = false;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $userAttributes = null;
        $createUser = (bool) ($this->data['create_user'] ?? false);

        if ($record->user_id || $createUser) {
            $userAttributes = [
                'email' => $data['user_email'] ?? $record->user?->email,
                'password' => $data['user_password'] ?? null,
                'name' => $data['name'] ?? $record->name,
            ];
        }

        unset($data['user_email'], $data['user_password'], $data['create_user']);

        return app(TeacherService::class)->update($record, $data, $userAttributes);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
