<?php

namespace App\Services;

use App\Enums\ExamResultStatus;
use App\Enums\NumberSequenceType;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    public function __construct(
        protected NumberGeneratorService $numbers,
    ) {}

    public function issueForExamResult(ExamResult $result, User $actor): Certificate
    {
        $result->loadMissing(['exam.batch.course', 'student']);

        if (! $result->isPublished()) {
            throw ValidationException::withMessages([
                'published_at' => 'Certificates can only be issued for published results.',
            ]);
        }

        if ($result->status !== ExamResultStatus::Pass) {
            throw ValidationException::withMessages([
                'status' => 'Certificates are only issued for passing students.',
            ]);
        }

        $course = $result->exam?->batch?->course;

        if (! $course) {
            throw ValidationException::withMessages([
                'course' => 'Exam batch has no course for certificate issuance.',
            ]);
        }

        return $this->issue($result->student, $course, $actor, $result->exam);
    }

    public function issue(Student $student, Course $course, User $actor, ?Exam $exam = null): Certificate
    {
        return DB::transaction(function () use ($student, $course, $actor, $exam) {
            $existing = Certificate::query()
                ->where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->when($exam, fn ($q) => $q->where('exam_id', $exam->id))
                ->first();

            if ($existing) {
                return $existing;
            }

            return Certificate::query()->create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'exam_id' => $exam?->id,
                'certificate_no' => $this->numbers->next(NumberSequenceType::Certificate),
                'verify_token' => Str::random(40),
                'issued_on' => now()->toDateString(),
                'issued_by' => $actor->id,
            ]);
        });
    }

    public function findPublic(string $certificateNo): ?Certificate
    {
        return Certificate::query()
            ->with(['student', 'course'])
            ->where('certificate_no', $certificateNo)
            ->first();
    }

    public function findByToken(string $token): ?Certificate
    {
        return Certificate::query()
            ->with(['student', 'course'])
            ->where('verify_token', $token)
            ->first();
    }
}
