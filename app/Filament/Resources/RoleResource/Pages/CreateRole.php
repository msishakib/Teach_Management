<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @var list<string>
     */
    protected array $selectedPermissions = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->selectedPermissions = RoleResource::extractPermissionNames(
            $this->form->getRawState()
        );

        unset($data['module_permissions']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Model $role */
        $role = static::getModel()::query()->create($data);
        $role->syncPermissions($this->selectedPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
