<?php

namespace App\Filament\Teacher\Pages;

use App\Models\TeacherSalary;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MySalarySlips extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Account';

    protected static ?string $navigationLabel = 'Salary slips';

    protected static ?string $title = 'My salary slips';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.teacher.pages.my-salary-slips';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getSalariesProperty(): LengthAwarePaginator
    {
        return TeacherSalary::query()
            ->with(['paymentVoucher'])
            ->where('teacher_id', Auth::user()->teacher->id)
            ->orderByDesc('month')
            ->paginate(25);
    }
}
