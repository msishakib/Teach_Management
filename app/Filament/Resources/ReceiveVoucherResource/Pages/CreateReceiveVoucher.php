<?php

namespace App\Filament\Resources\ReceiveVoucherResource\Pages;

use App\Filament\Resources\ReceiveVoucherResource;
use App\Models\Student;
use App\Services\ReceiveVoucherService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateReceiveVoucher extends CreateRecord
{
    protected static string $resource = ReceiveVoucherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $payerStudentId = $data['payer_student_id'] ?? null;
        $payer = $payerStudentId ? Student::query()->find($payerStudentId) : null;

        return app(ReceiveVoucherService::class)->create([
            'date' => $data['date'],
            'payer' => $payer,
            'account_id' => (int) $data['account_id'],
            'received_to_account_id' => (int) $data['received_to_account_id'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'attachment' => $data['attachment'] ?? null,
            'narration' => $data['narration'] ?? null,
            'created_by' => Auth::user(),
        ]);
    }
}
