<?php

namespace App\Filament\Student\Pages;

use App\Models\Order;
use App\Support\PortalAccess;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MyOrders extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Course orders';

    protected static ?string $title = 'My course orders';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.student.pages.my-orders';

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getOrdersProperty(): LengthAwarePaginator
    {
        return Order::query()
            ->with(['course', 'receipt'])
            ->orderByDesc('id')
            ->paginate(25);
    }
}
