<?php

namespace App\Filament\Pages;

use App\Services\TeacherSalaryService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class GenerateTeacherSalaries extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Generate salaries';

    protected static ?string $title = 'Generate teacher salaries';

    protected static ?int $navigationSort = 31;

    protected static string $view = 'filament.pages.generate-teacher-salaries';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('payment.create') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'month' => now()->format('Y-m'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('month')
                    ->label('Salary month')
                    ->placeholder('YYYY-MM')
                    ->required()
                    ->rule('date_format:Y-m')
                    ->helperText('Creates salary rows for all active teachers with a configured salary. Existing rows for the month are skipped.'),
            ])
            ->statePath('data');
    }

    public function generate(TeacherSalaryService $salaries): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        $salaries->dispatchGenerateForMonth($data['month'], Auth::user());

        Notification::make()
            ->title('Salary generation queued')
            ->body("Generating salaries for {$data['month']} in the background.")
            ->success()
            ->send();
    }
}
