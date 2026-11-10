<?php

namespace App\Filament\Resources\JournalVoucherResource\Pages;

use App\Filament\Resources\JournalVoucherResource;
use App\Services\AccountingService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditJournalVoucher extends EditRecord
{
    protected static string $resource = JournalVoucherResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->record->lines()
            ->orderBy('id')
            ->get()
            ->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ])
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $lines = collect($data['lines'] ?? [])
            ->map(fn (array $line) => [
                'account_id' => (int) $line['account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'description' => $line['description'] ?? null,
            ])
            ->values()
            ->all();

        return app(AccountingService::class)->updateDraft($record, [
            'entry_date' => $data['entry_date'],
            'narration' => $data['narration'] ?? null,
        ], $lines);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->record->isDraft(), 403);
    }
}
