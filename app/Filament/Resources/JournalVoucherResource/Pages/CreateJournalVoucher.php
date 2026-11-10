<?php

namespace App\Filament\Resources\JournalVoucherResource\Pages;

use App\Enums\VoucherType;
use App\Filament\Resources\JournalVoucherResource;
use App\Services\AccountingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateJournalVoucher extends CreateRecord
{
    protected static string $resource = JournalVoucherResource::class;

    protected function handleRecordCreation(array $data): Model
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

        return app(AccountingService::class)->createEntry([
            'entry_date' => $data['entry_date'],
            'voucher_type' => VoucherType::Journal,
            'narration' => $data['narration'] ?? null,
            'created_by' => Auth::user(),
        ], $lines);
    }
}
