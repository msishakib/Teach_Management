<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Enums\StudentInvoiceStatus;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Support\LedgerAccounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeePaymentService
{
    public function __construct(
        protected AccountingService $accounting,
        protected NumberGeneratorService $numbers,
    ) {}

    /**
     * Receive a payment voucher and allocate across outstanding invoices.
     *
     * @param  array{
     *     student_id: int,
     *     date?: string,
     *     method: PaymentMethod|string,
     *     account_id?: int,
     *     amount: float|int|string,
     *     allocations?: list<array{student_invoice_id: int, amount: float|int|string}>,
     *     idempotency_key?: ?string,
     *     created_by?: ?User|int,
     *     notes?: ?string,
     * }  $payload
     */
    public function receive(array $payload): Receipt
    {
        $idempotencyKey = isset($payload['idempotency_key']) && $payload['idempotency_key'] !== ''
            ? (string) $payload['idempotency_key']
            : null;

        if ($idempotencyKey !== null) {
            $existing = Receipt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load(['allocations.invoice', 'journalEntry', 'account', 'student']);
            }
        }

        $amount = number_format((float) $payload['amount'], 2, '.', '');

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $method = $payload['method'] instanceof PaymentMethod
            ? $payload['method']
            : PaymentMethod::from((string) $payload['method']);

        $createdBy = $payload['created_by'] ?? null;
        if ($createdBy instanceof User) {
            $createdBy = $createdBy->id;
        }

        return DB::transaction(function () use ($payload, $amount, $method, $idempotencyKey, $createdBy) {
            if ($idempotencyKey !== null) {
                $existing = Receipt::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing->load(['allocations.invoice', 'journalEntry', 'account', 'student']);
                }
            }

            $student = Student::query()->lockForUpdate()->findOrFail((int) $payload['student_id']);
            $cashAccount = $this->resolveCashAccount($method, $payload['account_id'] ?? null);

            $openInvoices = StudentInvoice::query()
                ->where('student_id', $student->id)
                ->whereIn('status', [StudentInvoiceStatus::Unpaid, StudentInvoiceStatus::Partial])
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $allocations = $this->buildAllocations(
                $openInvoices,
                $amount,
                $payload['allocations'] ?? null,
            );

            $allocatedTotal = collect($allocations)->reduce(
                fn (string $carry, array $row) => bcadd($carry, $row['amount'], 2),
                '0.00'
            );

            $advance = bcsub($amount, $allocatedTotal, 2);

            if (bccomp($advance, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated amount exceeds payment amount.',
                ]);
            }

            $receipt = Receipt::query()->create([
                'receipt_no' => $this->numbers->nextFeeReceipt(),
                'student_id' => $student->id,
                'date' => $payload['date'] ?? now()->toDateString(),
                'method' => $method,
                'account_id' => $cashAccount->id,
                'amount' => $amount,
                'status' => ReceiptStatus::Posted,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $createdBy,
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($allocations as $row) {
                /** @var StudentInvoice $invoice */
                $invoice = $openInvoices->firstWhere('id', $row['student_invoice_id']);

                ReceiptAllocation::query()->create([
                    'receipt_id' => $receipt->id,
                    'student_invoice_id' => $invoice->id,
                    'amount' => $row['amount'],
                ]);

                $invoice->paid = bcadd((string) $invoice->paid, $row['amount'], 2);
                $invoice->due = bcsub(
                    bcsub((string) $invoice->total, (string) $invoice->discount, 2),
                    (string) $invoice->paid,
                    2
                );
                $invoice->status = $invoice->recalculateStatus();
                $invoice->save();
            }

            $journal = $this->postReceiptJournal(
                receipt: $receipt,
                cashAccount: $cashAccount,
                allocatedTotal: $allocatedTotal,
                advance: $advance,
                studentId: $student->id,
                createdBy: $createdBy,
            );

            $receipt->update(['journal_entry_id' => $journal->id]);

            return $receipt->fresh(['allocations.invoice', 'journalEntry', 'account', 'student']) ?? $receipt;
        });
    }

    /**
     * Outstanding open invoices for a student (oldest first).
     *
     * @return Collection<int, StudentInvoice>
     */
    public function outstandingInvoices(int $studentId): Collection
    {
        return StudentInvoice::query()
            ->with(['items.feeType', 'batch'])
            ->where('student_id', $studentId)
            ->whereIn('status', [StudentInvoiceStatus::Unpaid, StudentInvoiceStatus::Partial])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, StudentInvoice>  $openInvoices
     * @param  list<array{student_invoice_id: int, amount: float|int|string}>|null  $manual
     * @return list<array{student_invoice_id: int, amount: string}>
     */
    protected function buildAllocations(Collection $openInvoices, string $amount, ?array $manual): array
    {
        if ($manual !== null && $manual !== []) {
            $allocations = [];
            $remainingPayment = $amount;

            foreach ($manual as $index => $row) {
                $invoiceId = (int) ($row['student_invoice_id'] ?? 0);
                $allocate = number_format((float) ($row['amount'] ?? 0), 2, '.', '');

                /** @var StudentInvoice|null $invoice */
                $invoice = $openInvoices->firstWhere('id', $invoiceId);

                if (! $invoice) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.student_invoice_id" => "Invoice [{$invoiceId}] is not an open invoice for this student.",
                    ]);
                }

                if (bccomp($allocate, '0', 2) <= 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => 'Allocation amount must be greater than zero.',
                    ]);
                }

                if (bccomp($allocate, (string) $invoice->due, 2) > 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => "Allocation {$allocate} exceeds invoice due {$invoice->due}.",
                    ]);
                }

                if (bccomp($allocate, $remainingPayment, 2) > 0) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.amount" => 'Allocations exceed the payment amount.',
                    ]);
                }

                $allocations[] = [
                    'student_invoice_id' => $invoiceId,
                    'amount' => $allocate,
                ];
                $remainingPayment = bcsub($remainingPayment, $allocate, 2);
            }

            return $allocations;
        }

        $allocations = [];
        $remaining = $amount;

        foreach ($openInvoices as $invoice) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $apply = bccomp($remaining, (string) $invoice->due, 2) > 0
                ? (string) $invoice->due
                : $remaining;

            if (bccomp($apply, '0', 2) <= 0) {
                continue;
            }

            $allocations[] = [
                'student_invoice_id' => $invoice->id,
                'amount' => $apply,
            ];
            $remaining = bcsub($remaining, $apply, 2);
        }

        return $allocations;
    }

    protected function resolveCashAccount(PaymentMethod $method, ?int $accountId): Account
    {
        if ($accountId) {
            $account = Account::query()->findOrFail($accountId);

            if (! $account->is_cash_or_bank || ! $account->isPostable()) {
                throw ValidationException::withMessages([
                    'account_id' => 'Payment account must be an active cash/bank account.',
                ]);
            }

            return $account;
        }

        $code = $method->defaultAccountCode();

        if (! $code) {
            throw ValidationException::withMessages([
                'account_id' => 'A cash/bank account is required for this payment method.',
            ]);
        }

        return LedgerAccounts::byCode($code);
    }

    protected function postReceiptJournal(
        Receipt $receipt,
        Account $cashAccount,
        string $allocatedTotal,
        string $advance,
        int $studentId,
        ?int $createdBy,
    ): \App\Models\JournalEntry {
        $receivable = LedgerAccounts::byCode(LedgerAccounts::STUDENT_RECEIVABLE);
        $lines = [
            [
                'account_id' => $cashAccount->id,
                'debit' => $receipt->amount,
                'credit' => 0,
                'student_id' => $studentId,
                'description' => "Receipt {$receipt->receipt_no}",
            ],
        ];

        if (bccomp($allocatedTotal, '0', 2) > 0) {
            $lines[] = [
                'account_id' => $receivable->id,
                'debit' => 0,
                'credit' => $allocatedTotal,
                'student_id' => $studentId,
                'description' => 'Student receivable settlement',
            ];
        }

        if (bccomp($advance, '0', 2) > 0) {
            $advanceAccount = LedgerAccounts::byCode(LedgerAccounts::ADVANCE_FROM_STUDENTS);
            $lines[] = [
                'account_id' => $advanceAccount->id,
                'debit' => 0,
                'credit' => $advance,
                'student_id' => $studentId,
                'description' => 'Advance from student',
            ];
        }

        // Edge case: payment with no open invoices — entire amount is advance.
        if (count($lines) < 2) {
            $advanceAccount = LedgerAccounts::byCode(LedgerAccounts::ADVANCE_FROM_STUDENTS);
            $lines[] = [
                'account_id' => $advanceAccount->id,
                'debit' => 0,
                'credit' => $receipt->amount,
                'student_id' => $studentId,
                'description' => 'Advance from student',
            ];
        }

        $entry = $this->accounting->createEntry([
            'entry_date' => $receipt->date->toDateString(),
            'voucher_type' => VoucherType::Receipt,
            'narration' => "Fee receipt {$receipt->receipt_no}",
            'reference' => $receipt,
            'created_by' => $createdBy,
        ], $lines);

        return $this->accounting->post($entry);
    }
}
