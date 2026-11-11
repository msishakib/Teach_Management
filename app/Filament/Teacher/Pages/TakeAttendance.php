<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Concerns\TakesBulkAttendance;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TakeAttendance extends Page implements HasForms
{
    use InteractsWithForms;
    use TakesBulkAttendance;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Teaching';

    protected static ?string $navigationLabel = 'Take attendance';

    protected static ?string $title = 'Take attendance';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.take-attendance';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->can('attendance.create') || $user?->can('attendance.update'));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountTakesBulkAttendance();
    }

    public function form(Form $form): Form
    {
        return $this->attendanceForm($form);
    }
}
