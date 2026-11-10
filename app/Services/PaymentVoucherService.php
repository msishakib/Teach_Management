<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentVoucherStatus;
use App\Enums\TeacherSalaryStatus;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\PaymentVoucher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Support\PermissionName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVoucherService
{
    public function __construct(
        protected AccountingService $accounting,
        protected AccountBalanceService $balances,
        protected NumberGeneratorService $numbers,
        protected SettingsService $settings,
    ) {}

    /**
     * @param  array{
     *     date?: string,
     *     payee?: ?Model,
     *     account_id: int,
     *     paid_from_account_id: int,
     *     amount: float|int|string,
     *     method: PaymentMethod|string,
     *     attachment?: ?string,
     *     narration?: ?string,
     *     teacher_salary_id?: ?int,
     *     created_by?: ?User|int,
     * }  $payload
     */
    public function create(array $payload): PaymentVoucher
    {
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

        return DB::transaction(function () use ($payload, $amount, $method, $createdBy) {
            $expense = Account::query()->findOrFail((int) $payload['account_id']);
            $paidFrom = Account::query()->findOrFail((int) $payload['paid_from_account_id']);

            $this->assertExpenseAccount($expense);
            $this->assertCashOrBankAccount($paidFrom);

            $payee = $payload['payee'] ?? null;

            $voucher = PaymentVoucher::query()->create([
                'voucher_no' => $this->numbers->nextPaymentVoucher(),
                'date' => $payload['date'] ?? now()->toDateString(),
                'payee_type' => $payee?->getMorphClass(),
                'payee_id' => $payee?->getKey(),
                'account_id' => $expense->id,
                'paid_from_account_id' => $paidFrom->id,
                'amount' => $amount,
                'method' => $method,
                'attachment' => $payload['attachment'] ?? null,
                'narration' => $payload['narration'] ?? null,
                'status' => PaymentVoucherStatus::Draft,
                'teacher_salary_id' => $payload['teacher_salary_id'] ?? null,
                'created_by' => $createdBy,
            ]);

            if (! empty($payload['teacher_salary_id'])) {
                $salary = TeacherSalary::query()->lockForUpdate()->findOrFail((int) $payload['teacher_salary_id']);
                $salary->update(['status' => TeacherSalaryStatus::VoucherCreated]);
            }

            return $voucher->load(['expenseAccount', 'paidFromAccount', 'payee', 'teacherSalary']);
        });
    }

    /**
     * Approve a draft voucher (payment.approve) and auto-post the journal:
     * Dr Expense / Cr Cash-Bank.
     */
    public function approve(User $actor, PaymentVoucher $voucher): PaymentVoucher
    {
        if (! $actor->can(PermissionName::make('payment', 'approve'))) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to approve payment vouchers.',
            ]);
        }

        return DB::transaction(function () use ($actor, $voucher) {
            /** @var PaymentVoucher $voucher */
            $voucher = PaymentVoucher::query()
                ->lockForUpdate()
                ->with(['expenseAccount', 'paidFromAccount', 'teacherSalary'])
                ->findOrFail($voucher->id);

            if (! $voucher->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft payment vouchers can be approved.',
                ]);
            }

            $paidFrom = Account::query()->lockForUpdate()->findOrFail($voucher->paid_from_account_id);
            $this->assertSufficientCash($paidFrom, $voucher->amount);

            $voucher->update([
                'status' => PaymentVoucherStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $entry = $this->accounting->createEntry([
                'entry_date' => $voucher->date->toDateString(),
                'voucher_type' => VoucherType::Payment,
                'narration' => $voucher->narration ?: "Payment voucher {$voucher->voucher_no}",
                'reference' => $voucher,
                'created_by' => $actor,
            ], [
                [
                    'account_id' => $voucher->account_id,
                    'debit' => $voucher->amount,
                    'credit' => 0,
                    'description' => $voucher->expenseAccount?->name ?? 'Expense',
                ],
                [
                    'account_id' => $voucher->paid_from_account_id,
                    'debit' => 0,
                    'credit' => $voucher->amount,
                    'description' => $voucher->paidFromAccount?->name ?? 'Cash/Bank',
                ],
            ]);

            $entry = $this->accounting->post($entry);

            $voucher->update([
                'status' => PaymentVoucherStatus::Posted,
                'journal_entry_id' => $entry->id,
                'posted_at' => now(),
            ]);

            if ($voucher->teacher_salary_id) {
                TeacherSalary::query()
                    ->whereKey($voucher->teacher_salary_id)
                    ->lockForUpdate()
                    ->first()
                    ?->update(['status' => TeacherSalaryStatus::Paid]);
            }

            return $voucher->fresh([
                'expenseAccount',
                'paidFromAccount',
                'payee',
                'journalEntry',
                'teacherSalary',
                'approvedBy',
            ]) ?? $voucher;
        });
    }

    protected function assertExpenseAccount(Account $account): void
    {
        if (! $account->isPostable() || $account->type !== AccountType::Expense) {
            throw ValidationException::withMessages([
                'account_id' => 'Expense account must be an active postable expense account.',
            ]);
        }
    }

    protected function assertCashOrBankAccount(Account $account): void
    {
        if (! $account->isPostable() || ! $account->is_cash_or_bank) {
            throw ValidationException::withMessages([
                'paid_from_account_id' => 'Paid-from account must be an active cash/bank account.',
            ]);
        }
    }

    protected function assertSufficientCash(Account $account, float|int|string $amount): void
    {
        $allowNegative = (bool) $this->settings->get('finance.allow_negative_cash', false);

        if ($allowNegative) {
            return;
        }

        if (! $this->balances->hasSufficientBalance($account, $amount)) {
            $balance = $this->balances->balance($account);

            throw ValidationException::withMessages([
                'amount' => "Insufficient balance in {$account->name}. Available {$balance}, required {$amount}.",
            ]);
        }
    }
}
