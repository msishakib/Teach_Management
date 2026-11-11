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

/**
 * Theme, social links and counters for the public website. Institute name, logo,
 * address and phone are still edited in the main Settings page.
 */
class ManageWebsiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Website settings';

    protected static ?string $title = 'Website settings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-website-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('website.update') ?? false;
    }

    public function mount(SettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(['website' => $settings->grouped()['website'] ?? []]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Theme & identity')
                    ->description('Institute name, logo, address, phone and e-mail live under System → Settings.')
                    ->schema([
                        Forms\Components\ColorPicker::make('website.primary_color')
                            ->label('Primary colour')
                            ->required()
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        Forms\Components\ColorPicker::make('website.accent_color')
                            ->label('Accent colour (gradient end)')
                            ->required()
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        Forms\Components\TextInput::make('website.tagline')
                            ->label('Tagline (English)')
                            ->maxLength(160),
                        Forms\Components\TextInput::make('website.tagline_bn')
                            ->label('Tagline (Bangla)')
                            ->maxLength(160),
                        Forms\Components\Textarea::make('website.meta_description')
                            ->label('SEO description')
                            ->helperText('Shown in Google results, about 150 characters.')
                            ->rows(2)
                            ->maxLength(300)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('website.meta_description_bn')
                            ->label('SEO description (Bangla)')
                            ->helperText('Used on the Bangla pages. Falls back to the Bangla tagline.')
                            ->rows(2)
                            ->maxLength(300)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contact & social links')
                    ->schema([
                        Forms\Components\TextInput::make('website.office_hours')->maxLength(120),
                        Forms\Components\TextInput::make('website.whatsapp')
                            ->label('WhatsApp number')
                            ->tel()
                            ->helperText('International format, e.g. 8801XXXXXXXXX')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('website.facebook')->url()->maxLength(255),
                        Forms\Components\TextInput::make('website.youtube')->url()->maxLength(255),
                        Forms\Components\TextInput::make('website.instagram')->url()->maxLength(255),
                        Forms\Components\TextInput::make('website.linkedin')->url()->maxLength(255),
                        Forms\Components\TextInput::make('website.twitter')->label('X / Twitter')->url()->maxLength(255),
                        Forms\Components\Textarea::make('website.map_embed_url')
                            ->label('Google Maps embed')
                            ->helperText('Paste the embed URL (or the whole <iframe> snippet) from Google Maps → Share → Embed. Leave empty to build a map from the address.')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Homepage counters')
                    ->description('Leave blank to show live numbers from the database.')
                    ->schema([
                        Forms\Components\TextInput::make('website.stat_students')->label('Students')->numeric()->integer()->minValue(0),
                        Forms\Components\TextInput::make('website.stat_teachers')->label('Teachers')->numeric()->integer()->minValue(0),
                        Forms\Components\TextInput::make('website.stat_courses')->label('Courses')->numeric()->integer()->minValue(0),
                        Forms\Components\TextInput::make('website.stat_success_rate')->label('Success rate (%)')->numeric()->integer()->minValue(0)->maxValue(100),
                    ])
                    ->columns(4),
            ]);
    }

    public function save(SettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $website = collect($state['website'] ?? [])
            ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
            ->all();

        $settings->setMany(['website' => $website]);

        Notification::make()
            ->title('Website settings saved')
            ->success()
            ->send();
    }
}
