<?php

namespace App\Filament\Student\Widgets;

use App\Services\DashboardMetricsService;
use App\Support\PortalAccess;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StudentNextClassWidget extends Widget
{
    protected static string $view = 'filament.student.widgets.student-next-class-widget';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $pollingInterval = '300s';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user || ! PortalAccess::isStudentOrGuardian($user)) {
            return false;
        }

        return $user->can('course.view') || $user->can('class_routine.view');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $studentIds = PortalAccess::accessibleStudentIds() ?? [];

        return [
            'summary' => app(DashboardMetricsService::class)->studentNextClass($studentIds),
        ];
    }
}
