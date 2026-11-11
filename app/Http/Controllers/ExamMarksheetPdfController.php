<?php

namespace App\Http\Controllers;

use App\Models\ExamResult;
use App\Services\ResultPdfService;
use App\Support\PermissionName;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamMarksheetPdfController extends Controller
{
    public function __invoke(ExamResult $examResult, ResultPdfService $pdfs): Response|StreamedResponse
    {
        $user = auth()->user();

        abort_unless(
            $user?->can(PermissionName::make('exam', 'view'))
                || $user?->can('view', $examResult),
            403
        );

        // Students hit the global scope; load via withoutGlobalScopes only for staff who already passed view.
        $result = ExamResult::withoutGlobalScopes()->with(['student', 'items'])->findOrFail($examResult->id);

        if ($user?->student && (int) $result->student_id !== (int) $user->student->id) {
            abort(403);
        }

        abort_unless($result->isPublished() || $user?->can(PermissionName::make('exam', 'update')), 403);

        return $pdfs->streamMarksheet($result);
    }
}
