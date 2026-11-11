<?php

namespace App\Filament\Teacher\Pages;

use App\Models\ClassRoutine;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MyRoutine extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Teaching';

    protected static ?string $navigationLabel = 'Class routine';

    protected static ?string $title = 'My class routine';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.teacher.pages.my-routine';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getRoutinesProperty(): LengthAwarePaginator
    {
        return ClassRoutine::query()
            ->with(['batch.course', 'subject'])
            ->where('teacher_id', Auth::user()->teacher->id)
            ->orderBy('day')
            ->orderBy('start_time')
            ->paginate(25);
    }
}
