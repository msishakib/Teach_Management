<?php

namespace App\Filament\Teacher\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\PortalAccess;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class PendingMarksEntryWidget extends Widget
{
    protected static string $view = 'filament.teacher.widgets.pending-marks-entry-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! PortalAccess::isTeacher($user)) {
            return false;
        }

        return $user->can('exam.view')
            || $user->can('exam.create')
            || $user->can('exam.update');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $teacherId = Auth::user()?->teacher?->id;

        return [
            'summary' => $teacherId
                ? app(DashboardMetricsService::class)->teacherPendingMarksEntry((int) $teacherId)
                : ['pending_subjects_count' => 0, 'pending_marks_count' => 0, 'items' => []],
        ];
    }
}
