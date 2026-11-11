<?php

namespace App\Filament\Teacher\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Account';

    protected static ?string $navigationLabel = 'My profile';

    protected static ?string $title = 'My profile';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.teacher.pages.my-profile';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->teacher;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $teacher = Auth::user()->teacher;

        $this->form->fill([
            'name' => $teacher->name,
            'email' => $teacher->email,
            'phone' => $teacher->phone,
            'employee_no' => $teacher->employee_no,
            'joining_date' => $teacher->joining_date?->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('employee_no')->disabled(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
                Forms\Components\DatePicker::make('joining_date')->disabled()->native(false),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function save(): void
    {
        $teacher = Auth::user()->teacher;
        $data = $this->form->getState();

        $teacher->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        Notification::make()->title('Profile updated')->success()->send();
    }
}
