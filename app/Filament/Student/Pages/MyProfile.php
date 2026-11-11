<?php

namespace App\Filament\Student\Pages;

use App\Support\PortalAccess;
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

    protected static ?string $navigationLabel = 'Profile';

    protected static ?string $title = 'Profile';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.student.pages.my-profile';

    public ?array $data = [];

    public bool $readOnly = false;

    public static function canAccess(): bool
    {
        return PortalAccess::isStudentOrGuardian();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $user = Auth::user();
        $this->readOnly = (bool) $user->guardian && ! $user->student;

        if ($user->student) {
            $student = $user->student;
            $this->form->fill([
                'admission_no' => $student->admission_no,
                'name' => $student->name,
                'email' => $student->email,
                'phone' => $student->phone,
                'address' => $student->address,
            ]);

            return;
        }

        $wards = $user->guardian?->students()->orderBy('name')->get() ?? collect();
        $this->form->fill([
            'guardian_name' => $user->guardian?->name,
            'guardian_phone' => $user->guardian?->phone,
            'wards' => $wards->map(fn ($s) => $s->admission_no.' — '.$s->name)->implode("\n"),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Student')
                    ->visible(fn () => (bool) Auth::user()?->student)
                    ->schema([
                        Forms\Components\TextInput::make('admission_no')->disabled(),
                        Forms\Components\TextInput::make('name')->required()->disabled(fn () => $this->readOnly),
                        Forms\Components\TextInput::make('email')->email()->disabled(fn () => $this->readOnly),
                        Forms\Components\TextInput::make('phone')->tel()->disabled(fn () => $this->readOnly),
                        Forms\Components\Textarea::make('address')->rows(3)->disabled(fn () => $this->readOnly)->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Guardian')
                    ->visible(fn () => (bool) Auth::user()?->guardian && ! Auth::user()?->student)
                    ->schema([
                        Forms\Components\TextInput::make('guardian_name')->disabled(),
                        Forms\Components\TextInput::make('guardian_phone')->disabled(),
                        Forms\Components\Textarea::make('wards')->label('Linked students')->disabled()->rows(4)->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_if($this->readOnly || ! Auth::user()?->student, 403);

        $data = $this->form->getState();
        Auth::user()->student->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        Notification::make()->title('Profile updated')->success()->send();
    }
}
