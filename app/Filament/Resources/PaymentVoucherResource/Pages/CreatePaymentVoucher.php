<?php

namespace App\Filament\Resources\PaymentVoucherResource\Pages;

use App\Filament\Resources\PaymentVoucherResource;
use App\Models\Teacher;
use App\Services\PaymentVoucherService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePaymentVoucher extends CreateRecord
{
    protected static string $resource = PaymentVoucherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $payeeTeacherId = $data['payee_teacher_id'] ?? null;
        $payee = $payeeTeacherId ? Teacher::query()->find($payeeTeacherId) : null;

        return app(PaymentVoucherService::class)->create([
            'date' => $data['date'],
            'payee' => $payee,
            'account_id' => (int) $data['account_id'],
            'paid_from_account_id' => (int) $data['paid_from_account_id'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'attachment' => $data['attachment'] ?? null,
            'narration' => $data['narration'] ?? null,
            'created_by' => Auth::user(),
        ]);
    }
}
