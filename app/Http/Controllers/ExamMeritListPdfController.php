<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\ResultPdfService;
use App\Support\PermissionName;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamMeritListPdfController extends Controller
{
    public function __invoke(Exam $exam, ResultPdfService $pdfs): Response|StreamedResponse
    {
        abort_unless(auth()->user()?->can(PermissionName::make('exam', 'view')), 403);

        return $pdfs->streamMeritList($exam);
    }
}
