<?php

namespace App\Services;

use App\Jobs\ExportFinancialReportJob;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExportService
{
    public const QUEUE_THRESHOLD_ROWS = 500;

    public function __construct(
        protected FinancialReportService $reports,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportOrQueue(User $user, string $reportType, string $format, array $filters): array
    {
        $preview = $this->rowsFor($reportType, $filters);
        $count = $preview instanceof Collection ? $preview->count() : (int) data_get($preview, 'row_count', 0);

        if ($count >= self::QUEUE_THRESHOLD_ROWS) {
            ExportFinancialReportJob::dispatch($user->id, $reportType, $format, $filters);

            return ['queued' => true, 'path' => null];
        }

        return ['queued' => false, 'path' => $this->export($reportType, $format, $filters)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(string $reportType, string $format, array $filters): string
    {
        $payload = $this->buildPayload($reportType, $filters);
        $filename = 'reports/'.$reportType.'-'.now()->format('YmdHis').'-'.Str::random(6).'.'.$format;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.financial-report', $payload);
            Storage::disk('local')->put($filename, $pdf->output());
        } else {
            Storage::disk('local')->put($filename, $this->toCsv($payload['rows'], $payload['columns']));
        }

        return $filename;
    }

    public function download(string $path): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, columns: list<string>, rows: Collection<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function buildPayload(string $reportType, array $filters): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return match ($reportType) {
            'ledger' => $this->tabular(
                'Ledger',
                ['entry_date', 'entry_no', 'voucher_type', 'narration', 'debit', 'credit', 'running_balance'],
                $this->reports->ledger((int) $filters['account_id'], $from, $to),
            ),
            'cash_book' => $this->tabular(
                'Cash book',
                ['entry_date', 'account_code', 'entry_no', 'voucher_type', 'debit', 'credit', 'running_balance'],
                $this->reports->cashBook($from, $to),
            ),
            'bank_book' => $this->tabular(
                'Bank book',
                ['entry_date', 'account_code', 'entry_no', 'voucher_type', 'debit', 'credit', 'running_balance'],
                $this->reports->bankBook($from, $to),
            ),
            'trial_balance' => $this->tabular(
                'Trial balance',
                ['code', 'name', 'type', 'opening_balance', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'],
                $this->reports->trialBalance($from, $to)['rows'],
                [
                    'total_debit' => $this->reports->trialBalance($from, $to)['total_debit'],
                    'total_credit' => $this->reports->trialBalance($from, $to)['total_credit'],
                ],
            ),
            'income_statement' => $this->incomePayload($from, $to),
            'balance_sheet' => $this->balanceSheetPayload($to),
            'daily_collection' => $this->tabular(
                'Daily collection',
                ['entry_date', 'total'],
                $this->reports->dailyCollection($from, $to),
            ),
            'student_dues' => $this->tabular(
                'Student due list',
                ['admission_no', 'name', 'invoice_count', 'total_due', 'earliest_due_date'],
                $this->reports->studentDueList(isset($filters['batch_id']) ? (int) $filters['batch_id'] : null),
            ),
            'student_statement' => $this->tabular(
                'Student statement',
                ['txn_date', 'reference', 'txn_type', 'particulars', 'debit', 'credit'],
                $this->reports->studentStatement((int) $filters['student_id'], $from, $to)['rows'],
            ),
            default => $this->tabular('Report', [], collect()),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function rowsFor(string $reportType, array $filters): Collection|array
    {
        $payload = $this->buildPayload($reportType, $filters);

        return $payload['rows'];
    }

    /**
     * @param  list<string>  $columns
     * @param  Collection<int, mixed>  $rows
     * @param  array<string, mixed>  $meta
     * @return array{title: string, columns: list<string>, rows: Collection<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function tabular(string $title, array $columns, Collection $rows, array $meta = []): array
    {
        $normalized = $rows->map(function ($row) use ($columns) {
            $data = (array) $row;
            $out = [];
            foreach ($columns as $column) {
                $out[$column] = $data[$column] ?? null;
            }

            return $out;
        });

        return [
            'title' => $title,
            'columns' => $columns,
            'rows' => $normalized,
            'meta' => $meta,
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: Collection<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function incomePayload(string $from, string $to): array
    {
        $report = $this->reports->incomeStatement($from, $to);
        $rows = $report['income']->map(fn ($r) => [
            'section' => 'Income',
            'code' => $r->code,
            'name' => $r->name,
            'balance' => $r->balance,
        ])->concat($report['expenses']->map(fn ($r) => [
            'section' => 'Expense',
            'code' => $r->code,
            'name' => $r->name,
            'balance' => $r->balance,
        ]));

        return $this->tabular('Income statement', ['section', 'code', 'name', 'balance'], $rows, [
            'total_income' => $report['total_income'],
            'total_expense' => $report['total_expense'],
            'net_profit' => $report['net_profit'],
        ]);
    }

    /**
     * @return array{title: string, columns: list<string>, rows: Collection<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function balanceSheetPayload(string $asOf): array
    {
        $report = $this->reports->balanceSheet($asOf);
        $map = fn (string $section, Collection $items) => $items->map(fn ($r) => [
            'section' => $section,
            'code' => $r->code,
            'name' => $r->name,
            'balance' => $r->balance,
        ]);

        $rows = $map('Assets', $report['assets'])
            ->concat($map('Liabilities', $report['liabilities']))
            ->concat($map('Equity', $report['equity']));

        return $this->tabular('Balance sheet', ['section', 'code', 'name', 'balance'], $rows, [
            'total_assets' => $report['total_assets'],
            'total_liabilities' => $report['total_liabilities'],
            'total_equity' => $report['total_equity'],
            'balanced' => $report['balanced'],
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    protected function toCsv(Collection $rows, array $columns): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row[$column] ?? '', $columns));
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
