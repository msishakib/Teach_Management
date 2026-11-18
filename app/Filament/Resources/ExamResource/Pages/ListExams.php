<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Filament\Resources\ExamResource;
use App\Models\Exam;
use App\Services\ExamMarksService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTableRecords(): Collection|Paginator
    {
        $records = parent::getTableRecords();

        $exams = $records instanceof Paginator
            ? $records->getCollection()
            : $records;

        if ($exams instanceof Collection && $exams->isNotEmpty()) {
            $progress = app(ExamMarksService::class)->progressForExams($exams);

            $exams->each(function (Exam $exam) use ($progress): void {
                $exam->marks_progress = $progress[$exam->id] ?? [
                    'subjects_total' => 0,
                    'subjects_complete' => 0,
                    'enrolled' => 0,
                    'percent' => 0.0,
                ];
            });
        }

        return $records;
    }
}
