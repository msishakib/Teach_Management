<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStandardTable;
use App\Filament\Resources\StudentRegistrationResource\Pages;
use App\Models\StudentRegistration;
use App\Services\StudentRegistrationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class StudentRegistrationResource extends Resource
{
    use HasStandardTable;

    protected static ?string $model = StudentRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Admissions';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Applicant')
                ->schema([
                    Forms\Components\TextInput::make('registration_no')->label('Registration no')->disabled(),
                    Forms\Components\TextInput::make('name')->disabled(),
                    Forms\Components\DatePicker::make('dob')->label('Date of birth')->disabled(),
                    Forms\Components\TextInput::make('gender')->disabled(),
                    Forms\Components\TextInput::make('phone')->disabled(),
                    Forms\Components\TextInput::make('email')->disabled(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Identity')
                ->schema([
                    Forms\Components\TextInput::make('national_id')->label('NID number')->disabled(),
                    Forms\Components\TextInput::make('birth_certificate_no')->label('Birth certificate no')->disabled(),
                    Forms\Components\TextInput::make('religion')->disabled(),
                    Forms\Components\TextInput::make('blood_group')->disabled(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Address')
                ->schema([
                    Forms\Components\Textarea::make('present_address')->rows(3)->disabled(),
                    Forms\Components\Textarea::make('permanent_address')->rows(3)->disabled(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Academic background')
                ->schema([
                    Forms\Components\TextInput::make('last_institution')->disabled(),
                    Forms\Components\TextInput::make('last_exam')->disabled(),
                    Forms\Components\TextInput::make('last_result')->disabled(),
                    Forms\Components\TextInput::make('passing_year')->disabled(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Review')
                ->schema([
                    Forms\Components\TextInput::make('status')->disabled(),
                    Forms\Components\Textarea::make('remarks')->rows(3)->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureStandardTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('registration_no')->label('Registration no')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_exam')->label('Last exam')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    StudentRegistration::STATUS_PENDING => 'Pending',
                    StudentRegistration::STATUS_APPROVED => 'Approved',
                    StudentRegistration::STATUS_REJECTED => 'Rejected',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve registration')
                    ->modalDescription('This creates the student and an optional user account. No enrollment or invoice is created.')
                    ->visible(fn (StudentRegistration $record) => $record->isPending())
                    ->action(function (StudentRegistration $record): void {
                        try {
                            $student = app(StudentRegistrationService::class)->approve($record, auth()->user());

                            Notification::make()
                                ->title('Registration approved')
                                ->body("Student {$student->admission_no} created.")
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Cannot approve registration')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->visible(fn (StudentRegistration $record) => $record->isPending())
                    ->action(function (StudentRegistration $record, array $data): void {
                        try {
                            app(StudentRegistrationService::class)->reject($record, $data['reason'], auth()->user());

                            Notification::make()
                                ->title('Registration rejected')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Cannot reject registration')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = StudentRegistration::query()
            ->where('status', StudentRegistration::STATUS_PENDING)
            ->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentRegistrations::route('/'),
        ];
    }
}
