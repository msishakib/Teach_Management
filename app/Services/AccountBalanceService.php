<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountBalanceDaily;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountBalanceService
{
    public const LARGE_RANGE_DAYS = 62;

    /**
     * Current ledger balance for an account (opening + posted movements).
     * Asset/Expense are debit-normal; Liability/Equity/Income are credit-normal.
     */
    public function balance(Account $account): string
    {
        return $this->balanceAsOf($account, null);
    }

    /**
     * Balance as of end of day (inclusive). Null date = all posted activity.
     */
    public function balanceAsOf(Account $account, CarbonInterface|string|null $asOf): string
    {
        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->where('journal_entries.status', JournalEntryStatus::Posted->value);

        if ($asOf !== null) {
            $query->whereDate('journal_entries.entry_date', '<=', $this->toDateString($asOf));
        }

        $totals = $query
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as debit_total, COALESCE(SUM(journal_lines.credit), 0) as credit_total')
            ->first();

        return $this->normalizeBalance(
            $account,
            (float) $account->opening_balance,
            (float) ($totals->debit_total ?? 0),
            (float) ($totals->credit_total ?? 0),
        );
    }

    /**
     * Posted debit/credit totals for a period. Uses daily summary for large ranges.
     *
     * @return array{debit: string, credit: string}
     */
    public function periodTotals(Account|int $account, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $accountId = $account instanceof Account ? $account->id : (int) $account;
        $fromDate = $this->toDateString($from);
        $toDate = $this->toDateString($to);

        if ($this->shouldUseDailySummary($fromDate, $toDate) && $this->dailySummaryAvailable()) {
            $totals = DB::table('account_balances_daily')
                ->where('account_id', $accountId)
                ->whereDate('date', '>=', $fromDate)
                ->whereDate('date', '<=', $toDate)
                ->selectRaw('COALESCE(SUM(debit_total), 0) as debit_total, COALESCE(SUM(credit_total), 0) as credit_total')
                ->first();
        } else {
            $totals = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $accountId)
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->whereDate('journal_entries.entry_date', '>=', $fromDate)
                ->whereDate('journal_entries.entry_date', '<=', $toDate)
                ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as debit_total, COALESCE(SUM(journal_lines.credit), 0) as credit_total')
                ->first();
        }

        return [
            'debit' => number_format((float) ($totals->debit_total ?? 0), 2, '.', ''),
            'credit' => number_format((float) ($totals->credit_total ?? 0), 2, '.', ''),
        ];
    }

    /**
     * Aggregate period totals for all accounts in one SQL query.
     *
     * @return Collection<int, object{account_id: int, debit_total: string|float, credit_total: string|float}>
     */
    public function periodTotalsByAccount(CarbonInterface|string $from, CarbonInterface|string $to): Collection
    {
        $fromDate = $this->toDateString($from);
        $toDate = $this->toDateString($to);

        if ($this->shouldUseDailySummary($fromDate, $toDate) && $this->dailySummaryAvailable()) {
            return DB::table('account_balances_daily')
                ->whereDate('date', '>=', $fromDate)
                ->whereDate('date', '<=', $toDate)
                ->groupBy('account_id')
                ->selectRaw('account_id, COALESCE(SUM(debit_total), 0) as debit_total, COALESCE(SUM(credit_total), 0) as credit_total')
                ->get();
        }

        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->whereDate('journal_entries.entry_date', '>=', $fromDate)
            ->whereDate('journal_entries.entry_date', '<=', $toDate)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as account_id, COALESCE(SUM(journal_lines.debit), 0) as debit_total, COALESCE(SUM(journal_lines.credit), 0) as credit_total')
            ->get();
    }

    /**
     * Movements before a date (exclusive), all accounts — for opening balances.
     *
     * @return Collection<int, object{account_id: int, debit_total: string|float, credit_total: string|float}>
     */
    public function totalsBeforeDateByAccount(CarbonInterface|string $before): Collection
    {
        $beforeDate = $this->toDateString($before);

        if ($this->shouldUseDailySummary('1970-01-01', $beforeDate) && $this->dailySummaryAvailable()) {
            return DB::table('account_balances_daily')
                ->where('date', '<', $beforeDate)
                ->groupBy('account_id')
                ->selectRaw('account_id, COALESCE(SUM(debit_total), 0) as debit_total, COALESCE(SUM(credit_total), 0) as credit_total')
                ->get();
        }

        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->where('journal_entries.entry_date', '<', $beforeDate)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as account_id, COALESCE(SUM(journal_lines.debit), 0) as debit_total, COALESCE(SUM(journal_lines.credit), 0) as credit_total')
            ->get();
    }

    public function hasSufficientBalance(Account $account, float|int|string $amount): bool
    {
        $needed = number_format((float) $amount, 2, '.', '');

        return bccomp($this->balance($account), $needed, 2) >= 0;
    }

    public function shouldUseDailySummary(string $from, string $to): bool
    {
        $days = (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days;

        return $days >= self::LARGE_RANGE_DAYS;
    }

    public function dailySummaryAvailable(): bool
    {
        return Schema::hasTable('account_balances_daily')
            && AccountBalanceDaily::query()->exists();
    }

    /**
     * Rebuild nightly summary rows from posted journal lines.
     *
     * @return int Number of upserted day/account rows
     */
    public function rebuildDailyBalances(?string $from = null, ?string $to = null): int
    {
        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalEntryStatus::Posted->value)
            ->groupBy('journal_lines.account_id', 'journal_entries.entry_date')
            ->orderBy('journal_entries.entry_date')
            ->selectRaw('journal_lines.account_id as account_id, journal_entries.entry_date as date, COALESCE(SUM(journal_lines.debit), 0) as debit_total, COALESCE(SUM(journal_lines.credit), 0) as credit_total');

        if ($from !== null) {
            $query->whereDate('journal_entries.entry_date', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('journal_entries.entry_date', '<=', $to);
        }

        $rows = $query->get();
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $payload[] = [
                'account_id' => (int) $row->account_id,
                'date' => $row->date,
                'debit_total' => number_format((float) $row->debit_total, 2, '.', ''),
                'credit_total' => number_format((float) $row->credit_total, 2, '.', ''),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($payload === []) {
            return 0;
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            AccountBalanceDaily::query()->upsert(
                $chunk,
                ['account_id', 'date'],
                ['debit_total', 'credit_total', 'updated_at'],
            );
        }

        return count($payload);
    }

    public function normalizeBalance(Account $account, float $opening, float $debit, float $credit): string
    {
        $openingS = number_format($opening, 2, '.', '');
        $debitS = number_format($debit, 2, '.', '');
        $creditS = number_format($credit, 2, '.', '');

        if (in_array($account->type, [AccountType::Asset, AccountType::Expense], true)) {
            return bcsub(bcadd($openingS, $debitS, 2), $creditS, 2);
        }

        return bcsub(bcadd($openingS, $creditS, 2), $debitS, 2);
    }

    public function isDebitNormal(AccountType|string $type): bool
    {
        $type = $type instanceof AccountType ? $type : AccountType::from($type);

        return in_array($type, [AccountType::Asset, AccountType::Expense], true);
    }

    protected function toDateString(CarbonInterface|string $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->toDateString();
        }

        return (string) $date;
    }
}
