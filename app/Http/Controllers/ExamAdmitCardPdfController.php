<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Student;
use App\Services\ExamPdfService;
use App\Support\PermissionName;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamAdmitCardPdfController extends Controller
{
    public function __invoke(Exam $exam, Student $student, ExamPdfService $pdfs): Response|StreamedResponse
    {
        abort_unless(
            auth()->user()?->can(PermissionName::make('exam', 'view')),
            403
        );

        $enrolled = $student->enrollments()
            ->where('batch_id', $exam->batch_id)
            ->exists();

        abort_unless($enrolled, 404);

        return $pdfs->streamAdmitCard($exam, $student);
    }
}
