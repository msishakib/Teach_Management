<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamPdfService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function downloadRoutine(Exam $exam): Response|StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.exam-routine', $this->routineData($exam));

        return $pdf->download($this->filename('routine', $exam));
    }

    public function streamRoutine(Exam $exam): Response|StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.exam-routine', $this->routineData($exam));

        return $pdf->stream($this->filename('routine', $exam));
    }

    public function downloadAdmitCard(Exam $exam, Student $student): Response|StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.exam-admit-card', $this->admitCardData($exam, $student));

        return $pdf->download($this->filename('admit-card', $exam, $student));
    }

    public function streamAdmitCard(Exam $exam, Student $student): Response|StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.exam-admit-card', $this->admitCardData($exam, $student));

        return $pdf->stream($this->filename('admit-card', $exam, $student));
    }

    /**
     * @return array<string, mixed>
     */
    protected function routineData(Exam $exam): array
    {
        $exam->loadMissing([
            'examType',
            'batch.course',
            'academicSession',
            'subjects.subject',
            'subjects.teacher',
        ]);

        $subjects = $exam->subjects
            ->sortBy(fn ($row) => $row->exam_date?->format('Y-m-d').' '.$row->start_time)
            ->values();

        return [
            'exam' => $exam,
            'subjects' => $subjects,
            'institute' => $this->institute(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function admitCardData(Exam $exam, Student $student): array
    {
        $exam->loadMissing([
            'examType',
            'batch.course',
            'academicSession',
            'subjects.subject',
            'subjects.teacher',
        ]);

        $subjects = $exam->subjects
            ->sortBy(fn ($row) => $row->exam_date?->format('Y-m-d').' '.$row->start_time)
            ->values();

        return [
            'exam' => $exam,
            'student' => $student,
            'subjects' => $subjects,
            'institute' => $this->institute(),
        ];
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

    protected function filename(string $kind, Exam $exam, ?Student $student = null): string
    {
        $base = "exam-{$kind}-{$exam->id}";

        if ($student) {
            $base .= '-'.$student->admission_no;
        }

        return $base.'.pdf';
    }
}
