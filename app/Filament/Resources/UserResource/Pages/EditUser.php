<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\UserService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(fn (Model $record) => app(UserService::class)->delete(
                    $record,
                    auth()->user(),
                )),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->roles()->pluck('id')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $roleIds = $data['roles'] ?? [];
        unset($data['roles']);

        return app(UserService::class)->update(
            $record,
            $data,
            is_array($roleIds) ? $roleIds : [],
        );
    }
}
