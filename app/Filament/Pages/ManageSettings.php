<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.manage-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('settings.update') ?? false;
    }

    public function mount(SettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($settings->grouped());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('general.institute_name')
                                    ->label('Institute name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\FileUpload::make('general.logo')
                                    ->label('Logo')
                                    ->image()
                                    ->directory('settings')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->nullable(),
                                Forms\Components\Textarea::make('general.address')
                                    ->label('Address')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('general.phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('general.email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('general.currency_symbol')
                                    ->label('Currency symbol')
                                    ->required()
                                    ->maxLength(8),
                                Forms\Components\TextInput::make('general.date_format')
                                    ->label('Date format')
                                    ->required()
                                    ->helperText('PHP date format, e.g. d M Y')
                                    ->maxLength(32),
                                Forms\Components\Select::make('general.timezone')
                                    ->label('Timezone')
                                    ->options(collect(timezone_identifiers_list())->mapWithKeys(fn (string $tz) => [$tz => $tz]))
                                    ->searchable()
                                    ->required(),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Academic')
                            ->schema([
                                Forms\Components\TextInput::make('academic.current_session')
                                    ->label('Current academic session')
                                    ->placeholder('2025-2026')
                                    ->maxLength(50),
                                Forms\Components\TagsInput::make('academic.month_names')
                                    ->label('Month names')
                                    ->helperText('Exactly 12 month names in order, starting from January.')
                                    ->columnSpanFull()
                                    ->required()
                                    ->rule('size:12'),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Finance')
                            ->schema([
                                Forms\Components\Select::make('finance.fiscal_year_start_month')
                                    ->label('Fiscal year start month')
                                    ->options([
                                        1 => 'January',
                                        2 => 'February',
                                        3 => 'March',
                                        4 => 'April',
                                        5 => 'May',
                                        6 => 'June',
                                        7 => 'July',
                                        8 => 'August',
                                        9 => 'September',
                                        10 => 'October',
                                        11 => 'November',
                                        12 => 'December',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('finance.receipt_prefix')
                                    ->label('Receipt prefix')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('finance.payment_prefix')
                                    ->label('Payment prefix')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('finance.voucher_number_format')
                                    ->label('Voucher number format')
                                    ->required()
                                    ->helperText('Tokens: {PREFIX}, {YEAR}, {NUMBER}')
                                    ->maxLength(100),
                                Forms\Components\Fieldset::make('Late fee rule')
                                    ->schema([
                                        Forms\Components\Select::make('finance.late_fee_rule.type')
                                            ->label('Type')
                                            ->options([
                                                'percentage' => 'Percentage',
                                                'fixed' => 'Fixed amount',
                                            ])
                                            ->required(),
                                        Forms\Components\TextInput::make('finance.late_fee_rule.value')
                                            ->label('Value')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('finance.late_fee_rule.grace_days')
                                            ->label('Grace days')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->integer(),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('finance.default_cash_account')
                                    ->label('Default cash account ID')
                                    ->numeric()
                                    ->nullable(),
                                Forms\Components\TextInput::make('finance.default_bank_account')
                                    ->label('Default bank account ID')
                                    ->numeric()
                                    ->nullable(),
                                Forms\Components\Toggle::make('finance.allow_negative_cash')
                                    ->label('Allow negative cash/bank balance')
                                    ->helperText('When off, payment voucher approval is blocked if the paid-from account has insufficient balance.')
                                    ->inline(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Attendance')
                            ->schema([
                                Forms\Components\TextInput::make('attendance.min_percent')
                                    ->label('Low attendance threshold (%)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100),
                                Forms\Components\Toggle::make('attendance.notify_guardian_on_absent')
                                    ->label('Notify guardians when a student is absent')
                                    ->helperText('Dispatches a queued notification to linked guardians.')
                                    ->inline(false),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Notification')
                            ->schema([
                                Forms\Components\Toggle::make('notification.sms_enabled')
                                    ->label('Enable SMS notifications')
                                    ->inline(false),
                                Forms\Components\Toggle::make('notification.email_enabled')
                                    ->label('Enable email notifications')
                                    ->inline(false),
                            ])
                            ->columns(2),

                        Forms\Components\Tabs\Tab::make('Numbering')
                            ->schema([
                                Forms\Components\TextInput::make('numbering.admission_prefix')
                                    ->label('Admission no prefix')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('numbering.employee_prefix')
                                    ->label('Employee no prefix')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('numbering.format')
                                    ->label('Number format')
                                    ->required()
                                    ->helperText('Tokens: {PREFIX}, {YEAR}, {NUMBER}')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('numbering.padding')
                                    ->label('Sequence padding')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->integer(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    public function save(SettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $state = $this->normalizeState($state);

        $settings->setMany($state);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function normalizeState(array $state): array
    {
        if (isset($state['finance']['fiscal_year_start_month'])) {
            $state['finance']['fiscal_year_start_month'] = (int) $state['finance']['fiscal_year_start_month'];
        }

        if (isset($state['finance']['late_fee_rule']['value'])) {
            $state['finance']['late_fee_rule']['value'] = (float) $state['finance']['late_fee_rule']['value'];
        }

        if (isset($state['finance']['late_fee_rule']['grace_days'])) {
            $state['finance']['late_fee_rule']['grace_days'] = (int) $state['finance']['late_fee_rule']['grace_days'];
        }

        foreach (['default_cash_account', 'default_bank_account'] as $accountKey) {
            if (! array_key_exists($accountKey, $state['finance'] ?? [])) {
                continue;
            }

            $raw = $state['finance'][$accountKey];
            $state['finance'][$accountKey] = ($raw !== null && $raw !== '') ? (int) $raw : null;
        }

        if (isset($state['numbering']['padding'])) {
            $state['numbering']['padding'] = (int) $state['numbering']['padding'];
        }

        if (isset($state['attendance']['min_percent'])) {
            $state['attendance']['min_percent'] = (float) $state['attendance']['min_percent'];
        }

        if (array_key_exists('notify_guardian_on_absent', $state['attendance'] ?? [])) {
            $state['attendance']['notify_guardian_on_absent'] = (bool) $state['attendance']['notify_guardian_on_absent'];
        }

        // Preserve yearly sequences managed by NumberGeneratorService.
        foreach (['admission_yearly_sequences', 'employee_yearly_sequences'] as $sequenceKey) {
            $state['numbering'][$sequenceKey] = setting("numbering.{$sequenceKey}", []);
        }

        return $state;
    }
}

