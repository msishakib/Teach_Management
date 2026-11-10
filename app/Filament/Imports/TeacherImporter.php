<?php

namespace App\Filament\Imports;

use App\Models\Teacher;
use App\Services\TeacherService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Validator;

class TeacherImporter extends Importer
{
    protected static ?string $model = Teacher::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('employee_no')
                ->label('Employee no')
                ->rules(['nullable', 'string', 'max:50', 'unique:teachers,employee_no']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('phone')
                ->rules(['nullable', 'string', 'max:50']),
            ImportColumn::make('joining_date')
                ->rules(['nullable', 'date']),
            ImportColumn::make('salary')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255', 'unique:users,email']),
        ];
    }

    public function resolveRecord(): ?Teacher
    {
        if (filled($this->data['employee_no'] ?? null)) {
            return Teacher::query()->firstOrNew([
                'employee_no' => $this->data['employee_no'],
            ]);
        }

        return new Teacher;
    }

    public function saveRecord(): void
    {
        if ($this->record->exists) {
            app(TeacherService::class)->update(
                $this->record,
                $this->record->only([
                    'name',
                    'phone',
                    'joining_date',
                    'salary',
                    'email',
                ]),
            );

            return;
        }

        $attributes = [
            'employee_no' => $this->data['employee_no'] ?? null,
            'name' => $this->data['name'],
            'phone' => $this->data['phone'] ?? null,
            'joining_date' => $this->data['joining_date'] ?? null,
            'salary' => $this->data['salary'] ?? null,
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

        if (blank($attributes['employee_no'])) {
            unset($attributes['employee_no']);
        }

        $this->record = app(TeacherService::class)->create($attributes, $userAttributes);
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

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your teacher import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
