<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\Student;
use App\Services\AttendanceService;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class BatchStudents extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'batch-students';

    protected static ?string $title = 'Batch students';

    protected static string $view = 'filament.teacher.pages.batch-students';

    public ?Batch $batch = null;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $batchId = (int) request()->query('batch');
        abort_unless($batchId > 0, 404);

        $this->batch = Batch::query()->with(['course', 'session'])->findOrFail($batchId);

        abort_unless(
            app(AttendanceService::class)->teacherCanAccessBatch(Auth::user(), $this->batch),
            403
        );
    }

    public function getStudentsProperty(): LengthAwarePaginator
    {
        return Student::query()
            ->select(['students.id', 'students.admission_no', 'students.name', 'students.phone', 'students.status'])
            ->join('enrollments', function ($join) {
                $join->on('enrollments.student_id', '=', 'students.id')
                    ->where('enrollments.batch_id', '=', $this->batch->id)
                    ->where('enrollments.status', '=', EnrollmentStatus::Active->value);
            })
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->orderBy('students.name')
            ->paginate(25);
    }
}
