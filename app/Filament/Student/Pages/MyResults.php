<?php

namespace App\Filament\Student\Pages;

use App\Models\ExamResult;
use App\Support\PortalAccess;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class MyResults extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Academics';

    protected static ?string $navigationLabel = 'Results';

    protected static ?string $title = 'Exam results';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.student.pages.my-results';

    public Collection $results;

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $studentIds = PortalAccess::accessibleStudentIds() ?? [];

        $this->results = $studentIds === []
            ? collect()
            : ExamResult::query()
                ->with(['student', 'exam.examType', 'items.examSubject.subject'])
                ->whereIn('student_id', $studentIds)
                ->orderByDesc('published_at')
                ->paginate(25)
                ->getCollection();
    }
}
