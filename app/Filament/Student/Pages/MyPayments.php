<?php

namespace App\Filament\Student\Pages;

use App\Models\Receipt;
use App\Support\PortalAccess;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MyPayments extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $title = 'Payments & receipts';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.student.pages.my-payments';

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getReceiptsProperty(): LengthAwarePaginator
    {
        return Receipt::query()
            ->with(['student', 'account', 'allocations.invoice'])
            ->orderByDesc('date')
            ->paginate(25);
    }
}
