<?php

namespace App\Filament\Student\Pages;

use App\Models\StudentInvoice;
use App\Support\PortalAccess;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MyInvoices extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $title = 'Invoices';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.student.pages.my-invoices';

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getInvoicesProperty(): LengthAwarePaginator
    {
        return StudentInvoice::query()
            ->with(['student', 'batch.course', 'items.feeType'])
            ->orderByDesc('due_date')
            ->paginate(25);
    }
}
