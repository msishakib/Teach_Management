<?php

namespace App\Filament\Pages;

use App\Services\HealthCheckService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SystemHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Health check';

    protected static ?string $title = 'System health';

    protected static ?int $navigationSort = 95;

    protected static string $view = 'filament.pages.system-health';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('health.view') ?? false;
    }

    public function mount(HealthCheckService $health): void
    {
        abort_unless(static::canAccess(), 403);

        $this->refreshReport($health);
    }

    public function refreshReport(?HealthCheckService $health = null): void
    {
        $health ??= app(HealthCheckService::class);

        $this->report = $health->report();

        if (func_num_args() === 0) {
            Notification::make()
                ->title('Health check refreshed')
                ->success()
                ->send();
        }
    }
}
