<?php

namespace App\Services\Lms;

use App\Enums\AcademicSessionStatus;
use App\Enums\BatchStatus;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\Student;
use App\Models\User;
use App\Services\NumberGeneratorService;

/**
 * Creates (or renews) the existing enrollment record for a self-service online
 * purchase. No separate LMS enrollment table is introduced.
 */
class OnlineEnrollmentService
{
    public function __construct(
        protected NumberGeneratorService $numbers,
    ) {}

    /**
     * Resolve (or create) the student profile used for online checkout / enrolment.
     */
    public function resolveStudent(User $user): Student
    {
        return $this->studentFor($user);
    }

    public function enroll(User $user, Course $course): Enrollment
    {
        $student = $this->studentFor($user);

        $existing = Enrollment::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('batch', fn ($query) => $query->where('course_id', $course->id))
            ->orderBy('id')
            ->first();

        if ($existing instanceof Enrollment) {
            return $this->applyOnlineTerms($existing, $course);
        }

        $batch = $this->batchFor($course);

        $enrollment = new Enrollment([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => EnrollmentStatus::Active,
            'enrolled_via' => EnrollmentSource::Online,
        ]);

        return $this->applyOnlineTerms($enrollment, $course);
    }

    protected function applyOnlineTerms(Enrollment $enrollment, Course $course): Enrollment
    {
        $enrollment->enrolled_via = EnrollmentSource::Online;

        if ($course->access_days !== null) {
            $enrollment->expires_at = now()->addDays((int) $course->access_days);
        }

        if (! $enrollment->exists) {
            $enrollment->enrolled_on ??= now()->toDateString();
        }

        $enrollment->status = EnrollmentStatus::Active;
        $enrollment->save();

        return $enrollment;
    }

    protected function studentFor(User $user): Student
    {
        $user->loadMissing(['student', 'guardian.students']);

        if ($user->student instanceof Student) {
            return $user->student;
        }

        if ($user->guardian && $user->guardian->students->isNotEmpty()) {
            return $user->guardian->students->first();
        }

        return Student::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'admission_no' => $this->numbers->nextAdmission(),
                'name' => $user->name,
                'email' => $user->email,
                'status' => StudentStatus::Active,
            ],
        );
    }

    protected function batchFor(Course $course): Batch
    {
        $batch = Batch::query()
            ->where('course_id', $course->id)
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();

        if ($batch instanceof Batch) {
            return $batch;
        }

        $session = AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first()
            ?? AcademicSession::query()->orderByDesc('start_date')->first();

        if (! $session instanceof AcademicSession) {
            $session = AcademicSession::query()->create([
                'name' => (string) now()->year,
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'status' => AcademicSessionStatus::Active,
                'is_current' => true,
            ]);
        }

        return Batch::query()->create([
            'name' => 'Online',
            'course_id' => $course->id,
            'session_id' => $session->id,
            'start_date' => now()->toDateString(),
            'capacity' => 0,
            'status' => BatchStatus::Open,
        ]);
    }
}
