<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class StudentsByCourseChart extends ChartWidget
{
    protected static ?string $heading = 'Students by course';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '200px';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Auth::user()?->can('course.view') ?? false;
    }

    public function getDescription(): ?string
    {
        $data = app(DashboardMetricsService::class)->studentsByCourse();

        return number_format($data['total_enrollments']).' active enrolled';
    }

    protected function getData(): array
    {
        $data = app(DashboardMetricsService::class)->studentsByCourse();

        return [
            'datasets' => [
                [
                    'label' => 'Active students',
                    'data' => $data['counts'],
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'maxBarThickness' => 28,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'ticks' => ['font' => ['size' => 10]],
                    'grid' => ['display' => false],
                ],
                'y' => [
                    'ticks' => ['font' => ['size' => 10], 'precision' => 0],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.15)'],
                ],
            ],
        ];
    }
}
