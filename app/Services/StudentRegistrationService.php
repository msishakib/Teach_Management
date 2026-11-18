<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Notifications\SendCredentialsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentRegistrationService
{
    public function __construct(
        protected StudentService $students,
    ) {}

    /**
     * Approve a public registration by creating the student (and optionally a
     * linked user account). No enrollment or invoice is created here.
     */
    public function approve(StudentRegistration $registration, ?User $actor = null): Student
    {
        [$student, $password] = DB::transaction(function () use ($registration, $actor) {
            $registration = StudentRegistration::query()
                ->lockForUpdate()
                ->findOrFail($registration->getKey());

            if ($registration->status === StudentRegistration::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'registration' => 'This registration has already been approved.',
                ]);
            }

            if ($registration->status === StudentRegistration::STATUS_REJECTED) {
                throw ValidationException::withMessages([
                    'registration' => 'A rejected registration cannot be approved.',
                ]);
            }

            if (filled($registration->email) && User::query()->where('email', $registration->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => "A user account with {$registration->email} already exists.",
                ]);
            }

            $password = Str::random(12);

            $userAttributes = filled($registration->email) ? [
                'email' => $registration->email,
                'password' => $password,
                'name' => $registration->name,
            ] : null;

            $student = $this->students->create([
                'name' => $registration->name,
                'dob' => $registration->dob,
                'gender' => $registration->gender,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'address' => $registration->present_address,
                'present_address' => $registration->present_address,
                'permanent_address' => $registration->permanent_address ?: $registration->present_address,
                'national_id' => $registration->national_id,
                'birth_certificate_no' => $registration->birth_certificate_no,
                'religion' => $registration->religion,
                'blood_group' => $registration->blood_group,
                'last_institution' => $registration->last_institution,
                'last_exam' => $registration->last_exam,
                'last_result' => $registration->last_result,
                'passing_year' => $registration->passing_year,
            ], $userAttributes);

            $registration->update([
                'status' => StudentRegistration::STATUS_APPROVED,
                'remarks' => $registration->remarks,
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
            ]);

            return [$student, $userAttributes !== null ? $password : null];
        });

        if ($password !== null && $student->user !== null) {
            try {
                $student->user->notify(new SendCredentialsNotification($password));
            } catch (\Throwable $exception) {
                // The account already exists; a mail outage must not roll back or hide the approval.
                logger()->warning('Could not email student credentials.', [
                    'user_id' => $student->user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $student;
    }

    public function reject(StudentRegistration $registration, string $reason, ?User $actor = null): void
    {
        if ($registration->status === StudentRegistration::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'registration' => 'An approved registration cannot be rejected.',
            ]);
        }

        $registration->update([
            'status' => StudentRegistration::STATUS_REJECTED,
            'remarks' => $reason,
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
        ]);
    }
}
