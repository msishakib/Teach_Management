<?php

namespace App\Filament\Teacher\Pages;

use App\Models\Batch;
use App\Services\AttendanceService;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MyBatches extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Teaching';

    protected static ?string $navigationLabel = 'My batches';

    protected static ?string $title = 'Assigned batches';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.teacher.pages.my-batches';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->teacher;
    }

    public function getBatchesProperty(): LengthAwarePaginator
    {
        $ids = Auth::user()->teacher->assignedBatchIds();

        return Batch::query()
            ->with(['course', 'session'])
            ->withCount(['enrollments'])
            ->whereIn('id', $ids ?: [0])
            ->orderBy('name')
            ->paginate(25);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        // Warm service cache path for assigned batches.
        app(AttendanceService::class)->batchesForActor(Auth::user());
    }
}
