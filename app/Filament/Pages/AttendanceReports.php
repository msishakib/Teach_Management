<?php

namespace App\Filament\Pages;

use App\Models\Batch;
use App\Services\AttendanceReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AttendanceReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Attendance reports';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.attendance-reports';

    public ?array $data = [];

    public string $reportType = 'summary';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public ?Collection $reportRows = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('attendance.view') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'report_type' => 'summary',
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'threshold' => (float) setting('attendance.min_percent', 75),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('report_type')
                    ->label('Report')
                    ->options([
                        'monthly' => 'Monthly attendance sheet',
                        'summary' => 'Per-student summary',
                        'absent_today' => 'Absent today',
                        'low' => 'Low attendance',
                    ])
                    ->required()
                    ->live()
                    ->native(false),
                Forms\Components\Select::make('batch_id')
                    ->label('Batch')
                    ->options(Batch::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(fn (Forms\Get $get) => $get('report_type') === 'monthly')
                    ->nullable(),
                Forms\Components\TextInput::make('year')
                    ->numeric()
                    ->required()
                    ->visible(fn (Forms\Get $get) => $get('report_type') === 'monthly'),
                Forms\Components\Select::make('month')
                    ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))]))
                    ->required()
                    ->visible(fn (Forms\Get $get) => $get('report_type') === 'monthly'),
                Forms\Components\TextInput::make('threshold')
                    ->label('Threshold %')
                    ->numeric()
                    ->visible(fn (Forms\Get $get) => $get('report_type') === 'low'),
            ])
            ->columns(4)
            ->statePath('data');
    }

    public function runReport(AttendanceReportService $reports): void
    {
        $state = $this->form->getState();
        $this->reportType = $state['report_type'];
        $this->report = [];
        $this->reportRows = collect();

        $batchId = isset($state['batch_id']) ? (int) $state['batch_id'] : null;

        match ($this->reportType) {
            'monthly' => $this->report = $reports->monthlyBatchSheet(
                Batch::query()->findOrFail($batchId),
                (int) $state['year'],
                (int) $state['month'],
            ),
            'summary' => $this->reportRows = $reports->studentSummaries($batchId),
            'absent_today' => $this->reportRows = $reports->absentToday($batchId),
            'low' => $this->reportRows = $reports->lowAttendance(
                $batchId,
                isset($state['threshold']) ? (float) $state['threshold'] : null,
            ),
            default => null,
        };
    }
}
