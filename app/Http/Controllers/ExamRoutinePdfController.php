<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\ExamPdfService;
use App\Support\PermissionName;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamRoutinePdfController extends Controller
{
    public function __invoke(Exam $exam, ExamPdfService $pdfs): Response|StreamedResponse
    {
        abort_unless(
            auth()->user()?->can(PermissionName::make('exam', 'view')),
            403
        );

        return $pdfs->streamRoutine($exam);
    }
}
