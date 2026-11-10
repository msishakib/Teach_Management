<?php

namespace App\Filament\Resources\ExamResource\Pages;

use App\Enums\ExamStatus;
use App\Filament\Resources\ExamResource;
use App\Services\ExamWorkflowService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['status'] = ExamStatus::Draft;

        return app(ExamWorkflowService::class)->create($data);
    }
}
