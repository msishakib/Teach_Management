<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdmissionsStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->can('student.view')
            || $user->can('enrollment.view')
            || $user->can('attendance.view');
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $metrics = app(DashboardMetricsService::class);
        $stats = [];

        if ($user?->can('student.view') || $user?->can('enrollment.view')) {
            $data = $metrics->admissionsAndApplications();
            $actionableApps = $data['pending_applications'] + $data['reviewing_applications'];

            $stats[] = Stat::make('New admissions', number_format($data['new_admissions_this_month']))
                ->description('This mo. · Last '.$data['new_admissions_last_month'])
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success');

            $stats[] = Stat::make('Pending applications', number_format($data['pending_applications']))
                ->description($data['reviewing_applications'] > 0
                    ? $data['reviewing_applications'].' reviewing'
                    : 'Awaiting review')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($actionableApps > 0 ? 'warning' : 'gray');
        }

        if ($user?->can('attendance.view')) {
            $summary = $metrics->todaysAttendanceSummary();
            $minPercent = (float) setting('attendance.min_percent', 75);
            $color = $summary['total_marked'] === 0
                ? 'gray'
                : ($summary['percentage'] >= $minPercent ? 'success' : 'danger');

            $stats[] = Stat::make("Today's attendance", number_format($summary['percentage'], 1).'%')
                ->description(sprintf(
                    '%d present · %d absent · %d session(s)',
                    $summary['attended_count'],
                    $summary['absent_count'],
                    $summary['sessions_count'],
                ))
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($color);
        }

        return $stats;
    }
}
