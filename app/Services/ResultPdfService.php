<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSubject;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultPdfService
{
    public function __construct(
        protected SettingsService $settings,
        protected ResultService $results,
    ) {}

    public function streamMarksheet(ExamResult $result): Response|StreamedResponse
    {
        $result->loadMissing([
            'student',
            'exam.examType',
            'exam.batch.course',
            'exam.academicSession',
            'exam.gradingScale.rules',
            'items.examSubject.subject',
        ]);

        $pdf = Pdf::loadView('pdf.exam-marksheet', [
            'result' => $result,
            'institute' => $this->institute(),
            'gradeRules' => $result->exam?->gradingScale?->rules
                ?? $result->exam?->gradingScale()->with('rules')->first()?->rules
                ?? collect(),
        ]);

        return $pdf->stream("marksheet-{$result->id}.pdf");
    }

    public function streamMeritList(Exam $exam): Response|StreamedResponse
    {
        $rows = $this->results->meritList($exam);

        $pdf = Pdf::loadView('pdf.exam-merit-list', [
            'exam' => $exam->loadMissing(['batch.course', 'examType', 'academicSession']),
            'rows' => $rows,
            'institute' => $this->institute(),
        ]);

        return $pdf->stream("merit-list-exam-{$exam->id}.pdf");
    }

    public function streamFailList(Exam $exam): Response|StreamedResponse
    {
        $rows = $this->results->failList($exam);

        $pdf = Pdf::loadView('pdf.exam-fail-list', [
            'exam' => $exam->loadMissing(['batch.course', 'examType']),
            'rows' => $rows,
            'institute' => $this->institute(),
        ]);

        return $pdf->stream("fail-list-exam-{$exam->id}.pdf");
    }

    public function streamSubjectReport(Exam $exam): Response|StreamedResponse
    {
        $exam->loadMissing(['subjects.subject']);

        $stats = $exam->subjects->map(function (ExamSubject $subject) {
            return [
                'subject' => $subject,
                'stats' => $this->results->subjectStats($subject),
            ];
        });

        $pdf = Pdf::loadView('pdf.exam-subject-stats', [
            'exam' => $exam,
            'stats' => $stats,
            'institute' => $this->institute(),
        ]);

        return $pdf->stream("subject-stats-exam-{$exam->id}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    protected function institute(): array
    {
        return [
            'name' => $this->settings->get('general.institute_name'),
            'address' => $this->settings->get('general.address'),
            'phone' => $this->settings->get('general.phone'),
            'email' => $this->settings->get('general.email'),
            'logo' => $this->settings->get('general.logo'),
        ];
    }
}
