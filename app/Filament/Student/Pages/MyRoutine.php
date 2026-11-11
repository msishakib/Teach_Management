<?php

namespace App\Filament\Student\Pages;

use App\Enums\EnrollmentStatus;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Support\PortalAccess;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MyRoutine extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'Routine';

    protected static ?string $title = 'Class routine';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.student.pages.my-routine';

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getRoutinesProperty(): LengthAwarePaginator
    {
        $studentIds = PortalAccess::accessibleStudentIds() ?? [];
        $batchIds = Enrollment::query()
            ->whereIn('student_id', $studentIds ?: [0])
            ->where('status', EnrollmentStatus::Active)
            ->pluck('batch_id')
            ->unique()
            ->all();

        return ClassRoutine::query()
            ->with(['batch', 'subject', 'teacher'])
            ->whereIn('batch_id', $batchIds ?: [0])
            ->orderBy('day')
            ->orderBy('start_time')
            ->paginate(25);
    }
}
