<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class FinanceStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->can('fee.view')
            || $user->can('voucher.view')
            || $user->can('payment.view')
            || $user->can('journal.view')
            || $user->can('account.view');
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $metrics = app(DashboardMetricsService::class);
        $stats = [];

        if ($user->can('fee.view') || $user->can('voucher.view')) {
            $collection = $metrics->todaysCollection();
            $diff = bcsub($collection['today_total'], $collection['yesterday_total'], 2);
            $up = bccomp($diff, '0', 2) >= 0;

            $stats[] = Stat::make("Today's collection", $metrics->formatMoney($collection['today_total']))
                ->description(sprintf(
                    '%d receipt(s) · Yday %s',
                    $collection['today_receipts'],
                    $metrics->formatMoney($collection['yesterday_total']),
                ))
                ->descriptionIcon($up ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($up ? 'success' : 'gray');
        }

        if ($user->can('fee.view')) {
            $due = $metrics->totalDueSummary();

            $stats[] = Stat::make('Total due', $metrics->formatMoney($due['total_due']))
                ->description(sprintf(
                    '%d student(s) · %d invoice(s)',
                    $due['students_with_due'],
                    $due['open_invoices'],
                ))
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color(bccomp($due['total_due'], '0', 2) > 0 ? 'warning' : 'success');
        }

        if ($user->can('payment.view') || $user->can('journal.view')) {
            $expense = $metrics->expenseThisMonth();
            $higher = bccomp($expense['this_month_expense'], $expense['last_month_expense'], 2) > 0;

            $stats[] = Stat::make('Expense this month', $metrics->formatMoney($expense['this_month_expense']))
                ->description('Last mo. '.$metrics->formatMoney($expense['last_month_expense']))
                ->descriptionIcon($higher ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($higher ? 'danger' : 'info');
        }

        if ($user->can('account.view')) {
            $cashBank = $metrics->cashBankBalance();

            $stats[] = Stat::make('Cash / bank', $metrics->formatMoney($cashBank['total_balance']))
                ->description(sprintf(
                    'Cash %s · Bank %s',
                    $metrics->formatMoney($cashBank['cash_balance']),
                    $metrics->formatMoney($cashBank['bank_balance']),
                ))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary');
        }

        return $stats;
    }
}
