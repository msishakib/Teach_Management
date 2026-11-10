<?php

namespace App\Filament\Imports;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\StudentService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('admission_no')
                ->label('Admission no')
                ->rules(['nullable', 'string', 'max:50', 'unique:students,admission_no']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('dob')
                ->label('Date of birth')
                ->rules(['nullable', 'date']),
            ImportColumn::make('gender')
                ->rules(['nullable', Rule::enum(Gender::class)]),
            ImportColumn::make('phone')
                ->rules(['nullable', 'string', 'max:50']),
            ImportColumn::make('address')
                ->rules(['nullable', 'string']),
            ImportColumn::make('status')
                ->rules(['nullable', Rule::enum(StudentStatus::class)]),
            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255', 'unique:users,email']),
        ];
    }

    public function resolveRecord(): ?Student
    {
        if (filled($this->data['admission_no'] ?? null)) {
            return Student::query()->firstOrNew([
                'admission_no' => $this->data['admission_no'],
            ]);
        }

        return new Student;
    }

    public function saveRecord(): void
    {
        if ($this->record->exists) {
            app(StudentService::class)->update(
                $this->record,
                $this->record->only([
                    'name',
                    'dob',
                    'gender',
                    'phone',
                    'address',
                    'status',
                    'email',
                ]),
            );

            return;
        }

        $attributes = [
            'admission_no' => $this->data['admission_no'] ?? null,
            'name' => $this->data['name'],
            'dob' => $this->data['dob'] ?? null,
            'gender' => $this->data['gender'] ?? null,
            'phone' => $this->data['phone'] ?? null,
            'address' => $this->data['address'] ?? null,
            'status' => $this->data['status'] ?? StudentStatus::Active->value,
            'email' => $this->data['email'] ?? null,
        ];

        $userAttributes = null;

        if (($this->options['create_user'] ?? false) && filled($this->data['email'] ?? null)) {
            $password = $this->options['default_password'] ?? null;

            Validator::validate(
                ['default_password' => $password],
                ['default_password' => ['required', 'string', 'min:8']],
            );

            $userAttributes = [
                'email' => $this->data['email'],
                'password' => $password,
                'name' => $this->data['name'],
            ];
        }

        if (blank($attributes['admission_no'])) {
            unset($attributes['admission_no']);
        }

        $this->record = app(StudentService::class)->create($attributes, $userAttributes);
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Toggle::make('create_user')
                ->label('Create linked user accounts')
                ->helperText('Requires an email column. Uses the default password below.')
                ->default(false),
            TextInput::make('default_password')
                ->label('Default password')
                ->password()
                ->revealable()
                ->required(fn ($get) => (bool) $get('create_user'))
                ->visible(fn ($get) => (bool) $get('create_user'))
                ->minLength(8),
        ];
    }

    public function getJobRetryUntil(): ?\Carbon\CarbonInterface
    {
        return now()->addDay();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your student import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
