<?php

namespace App\Console\Commands;

use App\Services\DashboardMetricsService;
use App\Services\FinancialReportService;
use App\Support\DateRange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceBenchmark extends Command
{
    protected $signature = 'performance:benchmark
        {--days=30 : Number of days to include in period reports}
        {--explain : Print EXPLAIN plans for the heaviest queries}';

    protected $description = 'Benchmark the heaviest report queries against the seeded dataset';

    public function handle(FinancialReportService $reports, DashboardMetricsService $metrics): int
    {
        Cache::flush();

        $days = max(1, (int) $this->option('days'));
        $from = now()->subDays($days)->toDateString();
        $to = now()->toDateString();

        $accountId = (int) DB::table('journal_lines')
            ->select('account_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('account_id')
            ->orderByDesc('aggregate')
            ->value('account_id');

        if ($accountId === 0) {
            $this->error('No journal lines found. Run PerformanceSeeder first.');

            return self::FAILURE;
        }

        $this->info("Account #{$accountId}, period {$from} to {$to}");
        $this->newLine();

        $rows = [];

        $bench = function (string $label, callable $callback) use (&$rows): void {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $start = microtime(true);
            $result = $callback();
            $elapsed = (microtime(true) - $start) * 1000;
            $queries = DB::getQueryLog();

            DB::disableQueryLog();

            $rows[] = [
                $label,
                number_format($elapsed, 1).' ms',
                count($queries),
                is_countable($result) ? count($result) : 1,
            ];
        };

        foreach ([
            'Ledger' => fn () => $reports->ledger($accountId, $from, $to),
            'Trial balance' => fn () => $reports->trialBalance($from, $to)['rows'],
            'Cash book' => fn () => $reports->cashBook($from, $to),
            'Daily collection' => fn () => $reports->dailyCollection($from, $to),
            'Month-wise collection' => fn () => $reports->monthWiseCollection((int) now()->year),
            'Student due list' => fn () => $reports->studentDueList(),
            'Dashboard: today collection' => fn () => $metrics->todaysCollection(),
            'Dashboard: students by course' => fn () => $metrics->studentsByCourse(),
        ] as $label => $callback) {
            $bench($label.' (cold)', $callback);
            $bench($label.' (warm)', $callback);
        }

        $this->table(['Query', 'Time', 'SQL queries', 'Rows'], $rows);

        if ($this->option('explain')) {
            $this->newLine();
            $this->explainLedger($accountId, $from, $to);
            $this->explainStudentDueList();
        }

        return self::SUCCESS;
    }

    protected function explainLedger(int $accountId, string $from, string $to): void
    {
        $this->info('EXPLAIN: ledger');

        $sql = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $accountId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.entry_date', '>=', $from)
            ->where('journal_entries.entry_date', '<', DateRange::endExclusive($to))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select([
                'journal_lines.id',
                'journal_entries.entry_date',
                'journal_lines.debit',
                'journal_lines.credit',
            ])
            ->toSql();

        foreach (DB::select('EXPLAIN '.$sql, [$accountId, 'posted', $from, $to]) as $row) {
            $this->line(json_encode($row));
        }
    }

    protected function explainStudentDueList(): void
    {
        $this->newLine();
        $this->info('EXPLAIN: student due list');

        $sql = DB::table('student_invoices')
            ->join('students', 'students.id', '=', 'student_invoices.student_id')
            ->whereIn('student_invoices.status', ['unpaid', 'partial'])
            ->where('student_invoices.due', '>', 0)
            ->whereNull('students.deleted_at')
            ->groupBy('students.id', 'students.admission_no', 'students.name')
            ->orderBy('students.name')
            ->selectRaw('students.id, COUNT(student_invoices.id) as invoice_count, SUM(student_invoices.due) as total_due, MIN(student_invoices.due_date) as earliest_due_date')
            ->toSql();

        foreach (DB::select('EXPLAIN '.$sql, ['unpaid', 'partial', 0]) as $row) {
            $this->line(json_encode($row));
        }
    }
}
