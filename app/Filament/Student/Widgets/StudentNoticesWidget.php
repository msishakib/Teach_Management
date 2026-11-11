<?php

namespace App\Filament\Student\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\PortalAccess;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StudentNoticesWidget extends Widget
{
    protected static string $view = 'filament.student.widgets.student-notices-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! PortalAccess::isStudentOrGuardian($user)) {
            return false;
        }

        return $user->can('course.view')
            || $user->can('enrollment.view')
            || $user->can('report.view');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();

        return [
            'summary' => $user
                ? app(DashboardMetricsService::class)->studentNotices($user)
                : ['total_count' => 0, 'items' => []],
        ];
    }
}
