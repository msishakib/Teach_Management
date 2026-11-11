<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Models\Batch;
use App\Models\Student;
use App\Services\FinancialReportExportService;
use App\Services\FinancialReportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Financial reports';

    protected static ?string $title = 'Financial reports';

    protected static ?int $navigationSort = 40;

    protected static string $view = 'filament.pages.financial-reports';

    public ?array $data = [];

    public string $reportType = 'trial_balance';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public ?Collection $reportRows = null;

    /**
     * @var list<array{month: string, total: float|string}>
     */
    public array $chartPoints = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('report.view') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'report_type' => 'trial_balance',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'year' => (int) now()->year,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('report_type')
                    ->label('Report')
                    ->options([
                        'ledger' => 'Ledger',
                        'cash_book' => 'Cash book',
                        'bank_book' => 'Bank book',
                        'trial_balance' => 'Trial balance',
                        'income_statement' => 'Income statement',
                        'balance_sheet' => 'Balance sheet',
                        'daily_collection' => 'Daily collection',
                        'month_collection' => 'Month-wise collection',
                        'student_dues' => 'Student due list',
                        'student_statement' => 'Student statement',
                    ])
                    ->required()
                    ->live()
                    ->native(false),
                Forms\Components\DatePicker::make('from')
                    ->native(false)
                    ->visible(fn (Forms\Get $get) => ! in_array($get('report_type'), ['balance_sheet', 'month_collection', 'student_dues'], true)),
                Forms\Components\DatePicker::make('to')
                    ->label(fn (Forms\Get $get) => $get('report_type') === 'balance_sheet' ? 'As of' : 'To')
                    ->native(false)
                    ->visible(fn (Forms\Get $get) => $get('report_type') !== 'month_collection' && $get('report_type') !== 'student_dues'),
                Forms\Components\TextInput::make('year')
                    ->numeric()
                    ->visible(fn (Forms\Get $get) => $get('report_type') === 'month_collection'),
                Forms\Components\Select::make('account_id')
                    ->label('Account')
                    ->options(fn () => Account::query()
                        ->where('is_group', false)
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('report_type') === 'ledger')
                    ->required(fn (Forms\Get $get) => $get('report_type') === 'ledger'),
                Forms\Components\Select::make('student_id')
                    ->label('Student')
                    ->options(fn () => Student::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('report_type') === 'student_statement')
                    ->required(fn (Forms\Get $get) => $get('report_type') === 'student_statement'),
                Forms\Components\Select::make('batch_id')
                    ->label('Batch')
                    ->options(fn () => Batch::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('report_type') === 'student_dues'),
            ])
            ->columns(4)
            ->statePath('data');
    }

    public function runReport(FinancialReportService $reports): void
    {
        $state = $this->form->getState();
        $this->reportType = $state['report_type'];
        $this->report = [];
        $this->reportRows = collect();
        $this->chartPoints = [];

        $from = $state['from'] ?? now()->startOfMonth()->toDateString();
        $to = $state['to'] ?? now()->toDateString();

        match ($this->reportType) {
            'ledger' => $this->reportRows = $reports->ledger((int) $state['account_id'], $from, $to),
            'cash_book' => $this->reportRows = $reports->cashBook($from, $to),
            'bank_book' => $this->reportRows = $reports->bankBook($from, $to),
            'trial_balance' => $this->report = $reports->trialBalance($from, $to),
            'income_statement' => $this->report = $reports->incomeStatement($from, $to),
            'balance_sheet' => $this->report = $reports->balanceSheet($to),
            'daily_collection' => $this->reportRows = $reports->dailyCollection($from, $to),
            'month_collection' => $this->chartPoints = $reports->monthWiseCollection((int) $state['year'])
                ->map(fn ($row) => ['month' => $row->month, 'total' => (float) $row->total])
                ->values()
                ->all(),
            'student_dues' => $this->reportRows = $reports->studentDueList(
                isset($state['batch_id']) ? (int) $state['batch_id'] : null,
            ),
            'student_statement' => $this->report = $reports->studentStatement((int) $state['student_id'], $from, $to),
            default => null,
        };

        if ($this->reportType === 'trial_balance') {
            $this->reportRows = $this->report['rows'] ?? collect();
        }

        if ($this->reportType === 'student_statement') {
            $this->reportRows = $this->report['rows'] ?? collect();
        }
    }

    public function exportCsv(FinancialReportExportService $exports): ?StreamedResponse
    {
        return $this->export($exports, 'csv');
    }

    public function exportPdf(FinancialReportExportService $exports): ?StreamedResponse
    {
        return $this->export($exports, 'pdf');
    }

    protected function export(FinancialReportExportService $exports, string $format): ?StreamedResponse
    {
        abort_unless(Auth::user()?->can('report.view') || Auth::user()?->can('report.export'), 403);

        $state = $this->form->getState();
        $result = $exports->exportOrQueue(Auth::user(), $state['report_type'], $format, $state);

        if ($result['queued']) {
            Notification::make()
                ->title('Export queued')
                ->body('You will be notified when the file is ready.')
                ->success()
                ->send();

            return null;
        }

        $path = $result['path'];

        Notification::make()
            ->title('Export ready')
            ->success()
            ->send();

        return Storage::disk('local')->download($path);
    }
}
