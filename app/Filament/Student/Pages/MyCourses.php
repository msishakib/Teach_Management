<?php

namespace App\Filament\Student\Pages;

use App\Models\Enrollment;
use App\Support\PortalAccess;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MyCourses extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'Courses';

    protected static ?string $title = 'Enrolled courses';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.student.pages.my-courses';

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getEnrollmentsProperty(): LengthAwarePaginator
    {
        return Enrollment::query()
            ->with(['student', 'batch.course', 'batch.session'])
            ->orderByDesc('enrolled_on')
            ->paginate(25);
    }
}
