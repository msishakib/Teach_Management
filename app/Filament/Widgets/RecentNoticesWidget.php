<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class RecentNoticesWidget extends Widget
{
    protected static string $view = 'filament.widgets.recent-notices-widget';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->can('website.view') || $user->can('report.create');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'summary' => app(DashboardMetricsService::class)->recentNoticesSummary(),
        ];
    }
}
