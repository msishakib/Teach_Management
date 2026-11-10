<?php

namespace App\Filament\Exports;

use App\Models\Student;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StudentExporter extends Exporter
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('admission_no'),
            ExportColumn::make('name'),
            ExportColumn::make('dob')->label('Date of birth'),
            ExportColumn::make('gender'),
            ExportColumn::make('phone'),
            ExportColumn::make('address'),
            ExportColumn::make('status'),
            ExportColumn::make('email'),
            ExportColumn::make('user.email')->label('User email'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your student export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
