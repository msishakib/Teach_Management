<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlyCollectionComparisonChart extends ChartWidget
{
    protected static ?string $heading = 'Collection: this vs last month';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '200px';

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
            || $user->can('report.view');
    }

    public function getDescription(): ?string
    {
        $metrics = app(DashboardMetricsService::class);
        $data = $metrics->monthlyCollectionComparison();

        return sprintf(
            '%s %s · %s %s',
            $data['this_month_label'],
            $metrics->formatMoney($data['this_month_total']),
            $data['last_month_label'],
            $metrics->formatMoney($data['last_month_total']),
        );
    }

    protected function getData(): array
    {
        $data = app(DashboardMetricsService::class)->monthlyCollectionComparison();

        return [
            'datasets' => [
                [
                    'label' => $data['this_month_label'],
                    'data' => $data['this_month'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                ],
                [
                    'label' => $data['last_month_label'],
                    'data' => $data['last_month'],
                    'borderColor' => '#64748b',
                    'backgroundColor' => 'rgba(100, 116, 139, 0.08)',
                    'borderDash' => [4, 4],
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'borderWidth' => 1.5,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'boxWidth' => 10,
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => ['font' => ['size' => 10], 'maxRotation' => 0],
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'ticks' => ['font' => ['size' => 10]],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.15)'],
                ],
            ],
        ];
    }
}
