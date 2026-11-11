<?php

namespace App\Filament\Teacher\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\PortalAccess;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class PendingAttendanceWidget extends Widget
{
    protected static string $view = 'filament.teacher.widgets.pending-attendance-widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! PortalAccess::isTeacher($user)) {
            return false;
        }

        return $user->can('attendance.view')
            || $user->can('attendance.create')
            || $user->can('attendance.update');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $teacherId = Auth::user()?->teacher?->id;

        return [
            'summary' => $teacherId
                ? app(DashboardMetricsService::class)->teacherPendingAttendance((int) $teacherId)
                : ['pending_today_count' => 0, 'open_sessions_count' => 0, 'items' => []],
        ];
    }
}
