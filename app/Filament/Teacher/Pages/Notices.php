<?php

namespace App\Filament\Teacher\Pages;

use App\Models\Notice;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class Notices extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Account';

    protected static ?string $navigationLabel = 'Notices';

    protected static ?string $title = 'Notices';

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.teacher.pages.notices';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getNoticesProperty(): LengthAwarePaginator
    {
        return Notice::query()
            ->published()
            ->visibleToPortalUser()
            ->with(['batch'])
            ->orderByDesc('published_at')
            ->paginate(25);
    }
}
