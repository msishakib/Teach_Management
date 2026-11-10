<?php

namespace App\Filament\Resources\TeacherSalaryResource\Pages;

use App\Filament\Pages\GenerateTeacherSalaries;
use App\Filament\Resources\TeacherSalaryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeacherSalaries extends ListRecords
{
    protected static string $resource = TeacherSalaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate salaries')
                ->icon('heroicon-o-queue-list')
                ->url(GenerateTeacherSalaries::getUrl())
                ->visible(fn () => GenerateTeacherSalaries::canAccess()),
        ];
    }
}
