<?php

namespace App\Filament\Exports;

use App\Models\Teacher;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TeacherExporter extends Exporter
{
    protected static ?string $model = Teacher::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('employee_no'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('joining_date'),
            ExportColumn::make('salary'),
            ExportColumn::make('email'),
            ExportColumn::make('user.email')->label('User email'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your teacher export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
