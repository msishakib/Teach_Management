<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Enums\StudentInvoiceStatus;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public const CACHE_SECONDS = 300;

    public function __construct(
        protected AccountBalanceService $balances,
        protected SettingsService $settings,
    ) {}

    /**
     * Account ledger with SQL window running balance (no PHP accumulation).
     *
     * @return Collection<int, object>
     */
    public function ledger(int $accountId, CarbonInterface|string $from, CarbonInterface|string $to): Collection
    {
        $fromDate = $this->date($from);
        $toDate = $this->date($to);

        return $this->remember("ledger:{$accountId}:{$fromDate}:{$toDate}", function () use ($accountId, $fromDate, $toDate) {
            $account = Account::query()->findOrFail($accountId);
            $opening = $this->openingBalanceForAccount($account, $fromDate);
            $signed = $this->balances->isDebitNormal($account->type)
                ? '(journal_lines.debit - journal_lines.credit)'
                : '(journal_lines.credit - journal_lines.debit)';

            return DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $accountId)
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->whereDate('journal_entries.entry_date', '>=', $fromDate)
                ->whereDate('journal_entries.entry_date', '<=', $toDate)
                ->orderBy('journal_entries.entry_date')
                ->orderBy('journal_lines.id')
                ->selectRaw("
                    journal_lines.id,
                    journal_entries.entry_date,
                    journal_entries.entry_no,
                    journal_entries.voucher_type,
                    journal_entries.narration,
                    journal_lines.description,
                    journal_lines.debit,
                    journal_lines.credit,
                    journal_lines.student_id,
                    ({$opening}) + SUM({$signed}) OVER (
                        ORDER BY journal_entries.entry_date, journal_lines.id
                        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                    ) as running_balance
                ")
                ->get();
        });
    }

    /**
     * Cash book — all cash/bank accounts with code cash, or is_cash_or_bank filtered to cash-like.
     *
     * @return Collection<int, object>
     */
    public function cashBook(CarbonInterface|string $from, CarbonInterface|string $to): Collection
    {
        return $this->cashOrBankBook($from, $to, cashOnly: true);
    }

    /**
     * @return Collection<int, object>
     */
    public function bankBook(CarbonInterface|string $from, CarbonInterface|string $to): Collection
    {
        return $this->cashOrBankBook($from, $to, cashOnly: false);
    }

    /**
     * Trial balance as of period end, with period movements.
     *
     * @return array{rows: Collection<int, object>, total_debit: string, total_credit: string}
     */
    public function trialBalance(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $fromDate = $this->date($from);
        $toDate = $this->date($to);

        return $this->remember("trial:{$fromDate}:{$toDate}", function () use ($fromDate, $toDate) {
            $before = $this->balances->totalsBeforeDateByAccount($fromDate)->keyBy('account_id');
            $period = $this->balances->periodTotalsByAccount($fromDate, $toDate)->keyBy('account_id');

            $rows = DB::table('accounts')
                ->where('is_group', false)
                ->orderBy('code')
                ->select(['id', 'code', 'name', 'type', 'opening_balance'])
                ->get()
                ->map(function (object $account) use ($before, $period) {
                    $openDebit = (float) ($before->get($account->id)->debit_total ?? 0);
                    $openCredit = (float) ($before->get($account->id)->credit_total ?? 0);
                    $periodDebit = (float) ($period->get($account->id)->debit_total ?? 0);
                    $periodCredit = (float) ($period->get($account->id)->credit_total ?? 0);

                    $openingBalance = $this->balances->normalizeBalance(
                        new Account(['type' => $account->type]),
                        (float) $account->opening_balance,
                        $openDebit,
                        $openCredit,
                    );

                    $closingBalance = $this->balances->normalizeBalance(
                        new Account(['type' => $account->type]),
                        (float) $account->opening_balance,
                        $openDebit + $periodDebit,
                        $openCredit + $periodCredit,
                    );

                    $debitNormal = $this->balances->isDebitNormal($account->type);

                    return (object) [
                        'account_id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'opening_balance' => $openingBalance,
                        'period_debit' => number_format($periodDebit, 2, '.', ''),
                        'period_credit' => number_format($periodCredit, 2, '.', ''),
                        'closing_debit' => $debitNormal && bccomp($closingBalance, '0', 2) >= 0
                            ? $closingBalance
                            : (! $debitNormal && bccomp($closingBalance, '0', 2) < 0 ? bcmul($closingBalance, '-1', 2) : '0.00'),
                        'closing_credit' => ! $debitNormal && bccomp($closingBalance, '0', 2) >= 0
                            ? $closingBalance
                            : ($debitNormal && bccomp($closingBalance, '0', 2) < 0 ? bcmul($closingBalance, '-1', 2) : '0.00'),
                        'closing_balance' => $closingBalance,
                    ];
                })
                ->filter(fn (object $row) => bccomp($row->period_debit, '0', 2) !== 0
                    || bccomp($row->period_credit, '0', 2) !== 0
                    || bccomp($row->opening_balance, '0', 2) !== 0
                    || bccomp($row->closing_balance, '0', 2) !== 0)
                ->values();

            // Trial totals from SQL on posted lines in period + opening presentation —
            // verify equation via sum of closing debit/credit columns.
            $totalDebit = $rows->reduce(fn (string $c, object $r) => bcadd($c, $r->closing_debit, 2), '0.00');
            $totalCredit = $rows->reduce(fn (string $c, object $r) => bcadd($c, $r->closing_credit, 2), '0.00');

            return [
                'rows' => $rows,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ];
        });
    }

    /**
     * Income statement for a period — SQL aggregates by account type.
     *
     * @return array{income: Collection<int, object>, expenses: Collection<int, object>, total_income: string, total_expense: string, net_profit: string}
     */
    public function incomeStatement(CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $fromDate = $this->date($from);
        $toDate = $this->date($to);

        return $this->remember("income:{$fromDate}:{$toDate}", function () use ($fromDate, $toDate) {
            $rows = $this->typePeriodBalances($fromDate, $toDate, [AccountType::Income->value, AccountType::Expense->value]);

            $income = $rows->where('type', AccountType::Income->value)->values();
            $expenses = $rows->where('type', AccountType::Expense->value)->values();

            $totalIncome = $income->reduce(fn (string $c, object $r) => bcadd($c, $r->balance, 2), '0.00');
            $totalExpense = $expenses->reduce(fn (string $c, object $r) => bcadd($c, $r->balance, 2), '0.00');

            return [
                'income' => $income,
                'expenses' => $expenses,
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_profit' => bcsub($totalIncome, $totalExpense, 2),
            ];
        });
    }

    /**
     * Balance sheet / financial position as of a date.
     * Equity includes current-period profit (fiscal year start → asOf).
     *
     * @return array{
     *     assets: Collection<int, object>,
     *     liabilities: Collection<int, object>,
     *     equity: Collection<int, object>,
     *     total_assets: string,
     *     total_liabilities: string,
     *     total_equity: string,
     *     current_period_profit: string,
     *     balanced: bool
     * }
     */
    public function balanceSheet(CarbonInterface|string $asOf): array
    {
        $asOfDate = $this->date($asOf);
        $fiscalStart = $this->fiscalYearStart($asOfDate);

        return $this->remember("bs:{$asOfDate}", function () use ($asOfDate, $fiscalStart) {
            $rows = $this->balancesAsOfByType($asOfDate, [
                AccountType::Asset->value,
                AccountType::Liability->value,
                AccountType::Equity->value,
            ]);

            $profit = $this->incomeStatement($fiscalStart, $asOfDate)['net_profit'];

            $assets = $rows->where('type', AccountType::Asset->value)->values();
            $liabilities = $rows->where('type', AccountType::Liability->value)->values();
            $equity = $rows->where('type', AccountType::Equity->value)->values();

            $equity = $equity->push((object) [
                'account_id' => null,
                'code' => 'PL',
                'name' => 'Current period profit/(loss)',
                'type' => AccountType::Equity->value,
                'balance' => $profit,
            ]);

            $totalAssets = $assets->reduce(fn (string $c, object $r) => bcadd($c, $r->balance, 2), '0.00');
            $totalLiabilities = $liabilities->reduce(fn (string $c, object $r) => bcadd($c, $r->balance, 2), '0.00');
            $totalEquity = $equity->reduce(fn (string $c, object $r) => bcadd($c, $r->balance, 2), '0.00');
            $rightSide = bcadd($totalLiabilities, $totalEquity, 2);

            return [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'current_period_profit' => $profit,
                'balanced' => bccomp($totalAssets, $rightSide, 2) === 0,
            ];
        });
    }

    /**
     * Daily collection from posted receipt vouchers into cash/bank accounts.
     *
     * @return Collection<int, object{entry_date: string, total: string|float}>
     */
    public function dailyCollection(CarbonInterface|string $from, CarbonInterface|string $to): Collection
    {
        $fromDate = $this->date($from);
        $toDate = $this->date($to);

        return $this->remember("daily_collection:{$fromDate}:{$toDate}", function () use ($fromDate, $toDate) {
            return DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->where('journal_entries.voucher_type', VoucherType::Receipt->value)
                ->where('accounts.is_cash_or_bank', true)
                ->whereDate('journal_entries.entry_date', '>=', $fromDate)
                ->whereDate('journal_entries.entry_date', '<=', $toDate)
                ->groupBy('journal_entries.entry_date')
                ->orderBy('journal_entries.entry_date')
                ->selectRaw('journal_entries.entry_date, COALESCE(SUM(journal_lines.debit), 0) as total')
                ->get();
        });
    }

    /**
     * Month-wise collection chart data for a calendar year.
     *
     * @return Collection<int, object{month: string, total: string|float}>
     */
    public function monthWiseCollection(int $year): Collection
    {
        return $this->remember("month_collection:{$year}", function () use ($year) {
            return DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->where('journal_entries.voucher_type', VoucherType::Receipt->value)
                ->where('accounts.is_cash_or_bank', true)
                ->whereYear('journal_entries.entry_date', $year)
                ->groupBy(DB::raw($this->monthExpression('journal_entries.entry_date')))
                ->orderBy(DB::raw($this->monthExpression('journal_entries.entry_date')))
                ->selectRaw($this->monthExpression('journal_entries.entry_date').' as month, COALESCE(SUM(journal_lines.debit), 0) as total')
                ->get();
        });
    }

    /**
     * Students with outstanding invoice due (SQL aggregate).
     *
     * @return Collection<int, object>
     */
    public function studentDueList(?int $batchId = null): Collection
    {
        $cacheKey = 'student_dues:'.($batchId ?? 'all');

        return $this->remember($cacheKey, function () use ($batchId) {
            $query = DB::table('student_invoices')
                ->join('students', 'students.id', '=', 'student_invoices.student_id')
                ->leftJoin('batches', 'batches.id', '=', 'student_invoices.batch_id')
                ->whereIn('student_invoices.status', [
                    StudentInvoiceStatus::Unpaid->value,
                    StudentInvoiceStatus::Partial->value,
                ])
                ->where('student_invoices.due', '>', 0)
                ->whereNull('students.deleted_at')
                ->groupBy('students.id', 'students.admission_no', 'students.name')
                ->orderBy('students.name')
                ->selectRaw('
                    students.id as student_id,
                    students.admission_no,
                    students.name,
                    COUNT(student_invoices.id) as invoice_count,
                    COALESCE(SUM(student_invoices.due), 0) as total_due,
                    MIN(student_invoices.due_date) as earliest_due_date
                ');

            if ($batchId) {
                $query->where('student_invoices.batch_id', $batchId);
            }

            return $query->get();
        });
    }

    /**
     * Per-student statement: invoices and receipt allocations in range (SQL unions).
     *
     * @return array{student: object|null, rows: Collection<int, object>, total_invoiced: string, total_paid: string, closing_due: string}
     */
    public function studentStatement(int $studentId, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $fromDate = $this->date($from);
        $toDate = $this->date($to);

        return $this->remember("student_statement:{$studentId}:{$fromDate}:{$toDate}", function () use ($studentId, $fromDate, $toDate) {
            $student = DB::table('students')
                ->where('id', $studentId)
                ->whereNull('deleted_at')
                ->select(['id', 'admission_no', 'name'])
                ->first();

            $invoices = DB::table('student_invoices')
                ->where('student_id', $studentId)
                ->whereDate('due_date', '>=', $fromDate)
                ->whereDate('due_date', '<=', $toDate)
                ->selectRaw("
                    due_date as txn_date,
                    invoice_no as reference,
                    'invoice' as txn_type,
                    total as debit,
                    0 as credit,
                    due as balance_hint,
                    month as particulars
                ");

            $payments = DB::table('receipt_allocations')
                ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
                ->join('student_invoices', 'student_invoices.id', '=', 'receipt_allocations.student_invoice_id')
                ->where('receipts.student_id', $studentId)
                ->where('receipts.status', 'posted')
                ->whereDate('receipts.date', '>=', $fromDate)
                ->whereDate('receipts.date', '<=', $toDate)
                ->selectRaw("
                    receipts.date as txn_date,
                    receipts.receipt_no as reference,
                    'receipt' as txn_type,
                    0 as debit,
                    receipt_allocations.amount as credit,
                    0 as balance_hint,
                    student_invoices.invoice_no as particulars
                ");

            $rows = DB::query()
                ->fromSub($invoices->unionAll($payments), 'student_statement')
                ->orderBy('txn_date')
                ->orderBy('reference')
                ->get();

            $totals = DB::table('student_invoices')
                ->where('student_id', $studentId)
                ->selectRaw('COALESCE(SUM(total), 0) as total_invoiced, COALESCE(SUM(paid), 0) as total_paid, COALESCE(SUM(due), 0) as closing_due')
                ->first();

            return [
                'student' => $student,
                'rows' => $rows,
                'total_invoiced' => number_format((float) ($totals->total_invoiced ?? 0), 2, '.', ''),
                'total_paid' => number_format((float) ($totals->total_paid ?? 0), 2, '.', ''),
                'closing_due' => number_format((float) ($totals->closing_due ?? 0), 2, '.', ''),
            ];
        });
    }

    /**
     * SQL-only check that posted journal debits equal credits.
     *
     * @return array{total_debit: string, total_credit: string, balanced: bool}
     */
    public function postedJournalTotals(): array
    {
        $totals = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->first();

        $debit = number_format((float) ($totals->total_debit ?? 0), 2, '.', '');
        $credit = number_format((float) ($totals->total_credit ?? 0), 2, '.', '');

        return [
            'total_debit' => $debit,
            'total_credit' => $credit,
            'balanced' => bccomp($debit, $credit, 2) === 0,
        ];
    }

    public function forgetCache(): void
    {
        // Cache keys are hashed with prefix; flush by tag if redis — otherwise TTL handles it.
        // Provide explicit forget for known prefixes via cache store flush in tests only.
    }

    /**
     * @return Collection<int, object>
     */
    protected function cashOrBankBook(CarbonInterface|string $from, CarbonInterface|string $to, bool $cashOnly): Collection
    {
        $fromDate = $this->date($from);
        $toDate = $this->date($to);
        $cacheKey = ($cashOnly ? 'cashbook' : 'bankbook').":{$fromDate}:{$toDate}";

        return $this->remember($cacheKey, function () use ($fromDate, $toDate, $cashOnly) {
            $accountQuery = Account::query()
                ->where('is_cash_or_bank', true)
                ->where('is_group', false);

            if ($cashOnly) {
                $accountQuery->whereIn('code', ['1110', '1130', '1135']);
            } else {
                $accountQuery->where('code', '1120');
            }

            $accountIds = $accountQuery->pluck('id');

            if ($accountIds->isEmpty()) {
                return collect();
            }

            $prior = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->whereIn('journal_lines.account_id', $accountIds)
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->where('journal_entries.entry_date', '<', $fromDate)
                ->groupBy('journal_lines.account_id')
                ->selectRaw('journal_lines.account_id, COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as prior_net');

            return DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->leftJoinSub($prior, 'prior_moves', function ($join) {
                    $join->on('prior_moves.account_id', '=', 'journal_lines.account_id');
                })
                ->whereIn('journal_lines.account_id', $accountIds)
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->whereDate('journal_entries.entry_date', '>=', $fromDate)
                ->whereDate('journal_entries.entry_date', '<=', $toDate)
                ->orderBy('journal_entries.entry_date')
                ->orderBy('journal_lines.id')
                ->selectRaw("
                    journal_lines.id,
                    accounts.code as account_code,
                    accounts.name as account_name,
                    journal_entries.entry_date,
                    journal_entries.entry_no,
                    journal_entries.voucher_type,
                    journal_entries.narration,
                    journal_lines.description,
                    journal_lines.debit,
                    journal_lines.credit,
                    (accounts.opening_balance + COALESCE(prior_moves.prior_net, 0))
                        + SUM(journal_lines.debit - journal_lines.credit) OVER (
                            PARTITION BY journal_lines.account_id
                            ORDER BY journal_entries.entry_date, journal_lines.id
                            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) as running_balance
                ")
                ->get();
        });
    }

    /**
     * @param  list<string>  $types
     * @return Collection<int, object>
     */
    protected function typePeriodBalances(string $fromDate, string $toDate, array $types): Collection
    {
        $driver = DB::getDriverName();
        $balanceExpr = $driver === 'sqlite'
            ? "CASE WHEN accounts.type IN ('income','liability','equity') THEN SUM(journal_lines.credit - journal_lines.debit) ELSE SUM(journal_lines.debit - journal_lines.credit) END"
            : "CASE WHEN accounts.type IN ('income','liability','equity') THEN SUM(journal_lines.credit - journal_lines.debit) ELSE SUM(journal_lines.debit - journal_lines.credit) END";

        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->whereDate('journal_entries.entry_date', '>=', $fromDate)
            ->whereDate('journal_entries.entry_date', '<=', $toDate)
            ->whereIn('accounts.type', $types)
            ->where('accounts.is_group', false)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->selectRaw("
                accounts.id as account_id,
                accounts.code,
                accounts.name,
                accounts.type,
                {$balanceExpr} as balance
            ")
            ->get()
            ->map(function (object $row) {
                $row->balance = number_format((float) $row->balance, 2, '.', '');

                return $row;
            });
    }

    /**
     * @param  list<string>  $types
     * @return Collection<int, object>
     */
    protected function balancesAsOfByType(string $asOfDate, array $types): Collection
    {
        $moves = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->whereDate('journal_entries.entry_date', '<=', $asOfDate)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('
                journal_lines.account_id,
                COALESCE(SUM(journal_lines.debit), 0) as debit_total,
                COALESCE(SUM(journal_lines.credit), 0) as credit_total
            ');

        return DB::table('accounts')
            ->leftJoinSub($moves, 'moves', function ($join) {
                $join->on('moves.account_id', '=', 'accounts.id');
            })
            ->whereIn('accounts.type', $types)
            ->where('accounts.is_group', false)
            ->orderBy('accounts.code')
            ->selectRaw("
                accounts.id as account_id,
                accounts.code,
                accounts.name,
                accounts.type,
                CASE
                    WHEN accounts.type IN ('asset', 'expense') THEN
                        accounts.opening_balance + COALESCE(moves.debit_total, 0) - COALESCE(moves.credit_total, 0)
                    ELSE
                        accounts.opening_balance + COALESCE(moves.credit_total, 0) - COALESCE(moves.debit_total, 0)
                END as balance
            ")
            ->get()
            ->map(function (object $row) {
                $row->balance = number_format((float) $row->balance, 2, '.', '');

                return $row;
            })
            ->filter(fn (object $row) => bccomp($row->balance, '0', 2) !== 0)
            ->values();
    }

    protected function openingBalanceForAccount(Account $account, string $fromDate): string
    {
        $before = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->where('journal_entries.entry_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as debit_total, COALESCE(SUM(journal_lines.credit), 0) as credit_total')
            ->first();

        return $this->balances->normalizeBalance(
            $account,
            (float) $account->opening_balance,
            (float) ($before->debit_total ?? 0),
            (float) ($before->credit_total ?? 0),
        );
    }

    protected function fiscalYearStart(string $asOfDate): string
    {
        $startMonth = max(1, min(12, (int) $this->settings->get('finance.fiscal_year_start_month', 7)));
        $asOf = Carbon::parse($asOfDate);
        $year = (int) $asOf->year;

        if ((int) $asOf->month < $startMonth) {
            $year--;
        }

        return sprintf('%04d-%02d-01', $year, $startMonth);
    }

    protected function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    protected function date(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function remember(string $key, callable $callback): mixed
    {
        return Cache::remember('financial_report:'.$key, self::CACHE_SECONDS, $callback);
    }
}
