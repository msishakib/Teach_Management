<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Services\AccountService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['kind'] = $this->record->kind()->value;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['kind'] = $this->data['kind'] ?? $record->kind()->value;

        return app(AccountService::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action): void {
                    if ($this->record->children()->exists() || $this->record->journalLines()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Account in use')
                            ->body('Accounts with children or journal lines cannot be deleted.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
