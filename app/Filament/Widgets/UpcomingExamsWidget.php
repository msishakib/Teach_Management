<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class UpcomingExamsWidget extends Widget
{
    protected static string $view = 'filament.widgets.upcoming-exams-widget';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Auth::user()?->can('exam.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'summary' => app(DashboardMetricsService::class)->upcomingExamsSummary(),
        ];
    }
}
