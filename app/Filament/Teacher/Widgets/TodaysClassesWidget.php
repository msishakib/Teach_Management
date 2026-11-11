<?php

namespace App\Filament\Teacher\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\PortalAccess;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TodaysClassesWidget extends Widget
{
    protected static string $view = 'filament.teacher.widgets.todays-classes-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! PortalAccess::isTeacher($user)) {
            return false;
        }

        return $user->can('class_routine.view');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $teacherId = Auth::user()?->teacher?->id;

        return [
            'summary' => $teacherId
                ? app(DashboardMetricsService::class)->teacherTodaysClasses((int) $teacherId)
                : ['classes_count' => 0, 'batches_count' => 0, 'first_start' => null, 'last_end' => null, 'items' => []],
        ];
    }
}
