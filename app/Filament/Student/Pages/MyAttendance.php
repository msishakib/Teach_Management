<?php

namespace App\Filament\Student\Pages;

use App\Models\StudentAttendance;
use App\Support\PortalAccess;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MyAttendance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $title = 'Attendance summary';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.student.pages.my-attendance';

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getSummaryProperty(): array
    {
        $studentIds = PortalAccess::accessibleStudentIds() ?? [];

        return StudentAttendance::query()
            ->select('student_attendances.status', DB::raw('count(*) as total'))
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'student_attendances.attendance_session_id')
            ->whereIn('student_attendances.student_id', $studentIds ?: [0])
            ->groupBy('student_attendances.status')
            ->pluck('total', 'status')
            ->all();
    }

    public function getRowsProperty(): LengthAwarePaginator
    {
        $studentIds = PortalAccess::accessibleStudentIds() ?? [];

        return StudentAttendance::query()
            ->with(['student', 'session.batch', 'session.subject'])
            ->whereIn('student_id', $studentIds ?: [0])
            ->whereHas('session')
            ->latest('id')
            ->paginate(25);
    }
}
