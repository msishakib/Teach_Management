<?php

namespace App\Filament\Resources\TeacherAttendanceResource\Pages;

use App\Filament\Resources\TeacherAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeacherAttendance extends EditRecord
{
    protected static string $resource = TeacherAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
