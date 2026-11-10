<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReceiveVoucherStatus;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\ReceiveVoucher;
use App\Models\User;
use App\Support\PermissionName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveVoucherService
{
    public function __construct(
        protected AccountingService $accounting,
        protected NumberGeneratorService $numbers,
    ) {}

    /**
     * @param  array{
     *     date?: string,
     *     payer?: ?Model,
     *     account_id: int,
     *     received_to_account_id: int,
     *     amount: float|int|string,
     *     method: PaymentMethod|string,
     *     attachment?: ?string,
     *     narration?: ?string,
     *     created_by?: ?User|int,
     * }  $payload
     */
    public function create(array $payload): ReceiveVoucher
    {
        $amount = number_format((float) $payload['amount'], 2, '.', '');

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Receive amount must be greater than zero.',
            ]);
        }

        $method = $payload['method'] instanceof PaymentMethod
            ? $payload['method']
            : PaymentMethod::from((string) $payload['method']);

        $createdBy = $payload['created_by'] ?? null;
        if ($createdBy instanceof User) {
            $createdBy = $createdBy->id;
        }

        return DB::transaction(function () use ($payload, $amount, $method, $createdBy) {
            $creditAccount = Account::query()->findOrFail((int) $payload['account_id']);
            $receivedTo = Account::query()->findOrFail((int) $payload['received_to_account_id']);

            $this->assertCreditAccount($creditAccount);
            $this->assertCashOrBankAccount($receivedTo);

            $payer = $payload['payer'] ?? null;

            $voucher = ReceiveVoucher::query()->create([
                'voucher_no' => $this->numbers->nextReceiveVoucher(),
                'date' => $payload['date'] ?? now()->toDateString(),
                'payer_type' => $payer?->getMorphClass(),
                'payer_id' => $payer?->getKey(),
                'account_id' => $creditAccount->id,
                'received_to_account_id' => $receivedTo->id,
                'amount' => $amount,
                'method' => $method,
                'attachment' => $payload['attachment'] ?? null,
                'narration' => $payload['narration'] ?? null,
                'status' => ReceiveVoucherStatus::Draft,
                'created_by' => $createdBy,
            ]);

            return $voucher->load(['creditAccount', 'receivedToAccount', 'payer']);
        });
    }

    /**
     * Approve a draft voucher and auto-post the journal:
     * Dr Cash-Bank / Cr Income (or other credit account).
     */
    public function approve(User $actor, ReceiveVoucher $voucher): ReceiveVoucher
    {
        if (! $actor->can(PermissionName::make('voucher', 'approve'))) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to approve receive vouchers.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher) {
            /** @var ReceiveVoucher $voucher */
            $voucher = ReceiveVoucher::query()
                ->lockForUpdate()
                ->with(['creditAccount', 'receivedToAccount'])
                ->findOrFail($voucher->id);

            if (! $voucher->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft receive vouchers can be approved.',
                ]);
            }

            $this->assertCreditAccount(Account::query()->findOrFail($voucher->account_id));
            $this->assertCashOrBankAccount(Account::query()->findOrFail($voucher->received_to_account_id));

            $voucher->update([
                'status' => ReceiveVoucherStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $entry = $this->accounting->createEntry([
                'entry_date' => $voucher->date->toDateString(),
                'voucher_type' => VoucherType::Receipt,
                'narration' => $voucher->narration ?: "Receive voucher {$voucher->voucher_no}",
                'reference' => $voucher,
                'created_by' => $actor,
            ], [
                [
                    'account_id' => $voucher->received_to_account_id,
                    'debit' => $voucher->amount,
                    'credit' => 0,
                    'description' => $voucher->receivedToAccount?->name ?? 'Cash/Bank',
                ],
                [
                    'account_id' => $voucher->account_id,
                    'debit' => 0,
                    'credit' => $voucher->amount,
                    'description' => $voucher->creditAccount?->name ?? 'Credit account',
                ],
            ]);

            $entry = $this->accounting->post($entry);

            $voucher->update([
                'status' => ReceiveVoucherStatus::Posted,
                'journal_entry_id' => $entry->id,
                'posted_at' => now(),
            ]);

            return $voucher->fresh([
                'creditAccount',
                'receivedToAccount',
                'payer',
                'journalEntry',
                'approvedBy',
            ]) ?? $voucher;
        });
    }

    protected function assertCreditAccount(Account $account): void
    {
        if (! $account->isPostable()) {
            throw ValidationException::withMessages([
                'account_id' => 'Credit account must be an active postable ledger.',
            ]);
        }

        if ($account->is_cash_or_bank) {
            throw ValidationException::withMessages([
                'account_id' => 'Credit account cannot be a cash/bank account. Use a journal voucher for transfers.',
            ]);
        }
    }

    protected function assertCashOrBankAccount(Account $account): void
    {
        if (! $account->isPostable() || ! $account->is_cash_or_bank) {
            throw ValidationException::withMessages([
                'received_to_account_id' => 'Received-to account must be an active cash/bank account.',
            ]);
        }
    }
}
