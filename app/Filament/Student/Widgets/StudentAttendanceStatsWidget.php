<?php

namespace App\Filament\Student\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\PortalAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Compact attendance + due fees in one row of two stats.
 */
class StudentAttendanceStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! PortalAccess::isStudentOrGuardian($user)) {
            return false;
        }

        return $user->can('enrollment.view')
            || $user->can('attendance.view')
            || $user->can('fee.view');
    }

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $studentIds = PortalAccess::accessibleStudentIds() ?? [];
        $metrics = app(DashboardMetricsService::class);
        $stats = [];

        if ($user?->can('enrollment.view') || $user?->can('attendance.view')) {
            $summary = $metrics->studentAttendanceSummary($studentIds);
            $minPercent = (float) setting('attendance.min_percent', 75);
            $color = $summary['total_sessions'] === 0
                ? 'gray'
                : ($summary['percentage'] >= $minPercent ? 'success' : 'danger');

            $stats[] = Stat::make('Attendance', number_format($summary['percentage'], 1).'%')
                ->description(sprintf(
                    '%d/%d · %d absent · %d late',
                    $summary['attended_count'],
                    $summary['total_sessions'],
                    $summary['absent_count'],
                    $summary['late_count'],
                ))
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($color);
        }

        if ($user?->can('fee.view')) {
            $due = $metrics->studentDueFees($studentIds);
            $hasDue = bccomp($due['total_due'], '0', 2) > 0;
            $description = $hasDue && $due['next_due_date']
                ? sprintf(
                    '%d invoice(s) · Due %s',
                    $due['open_invoices_count'],
                    Carbon::parse($due['next_due_date'])->format('d M Y'),
                )
                : 'All settled';

            $stats[] = Stat::make('Due fees', $metrics->formatMoney($due['total_due']))
                ->description($description)
                ->descriptionIcon($hasDue ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($hasDue ? 'warning' : 'success');
        }

        return $stats;
    }
}
