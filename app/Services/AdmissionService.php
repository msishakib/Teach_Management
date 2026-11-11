<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\FeeInvoiceService;
use App\Services\StudentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    public function __construct(
        protected StudentService $students,
        protected NumberGeneratorService $numbers,
        protected FeeInvoiceService $invoices,
    ) {}

    public function approve(AdmissionApplication $application, ?User $actor = null): void
    {
        DB::transaction(function () use ($application, $actor) {
            if ($application->status === 'approved') {
                throw ValidationException::withMessages(['application' => 'Already approved.']);
            }

            $password = Str::random(10);

            $userAttrs = null;
            if (! blank($application->email)) {
                $userAttrs = [
                    'email' => $application->email,
                    'password' => $password,
                    'name' => $application->name,
                ];
            }

            $student = $this->students->create([
                'name' => $application->name,
                'dob' => $application->dob,
                'gender' => $application->gender,
                'email' => $application->email,
                'phone' => $application->phone,
                'address' => $application->address,
            ], $userAttrs, array_filter([[
                'name' => $application->guardian_name,
                'phone' => $application->guardian_phone,
                'relation' => 'guardian',
            ]]));

            // create enrollment if batch provided
            if ($application->batch_id) {
                $enrollment = Enrollment::query()->create([
                    'student_id' => $student->id,
                    'batch_id' => $application->batch_id,
                    'enrolled_on' => now()->toDateString(),
                    'status' => \App\Enums\EnrollmentStatus::Active,
                ]);

                // create invoice for enrollment (may include one-time admission fee)
                $this->invoices->generateForEnrollment($enrollment, now()->format('Y-m'), $actor);
            }

            // mark application approved
            $application->update([
                'status' => 'approved',
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
                'remarks' => $application->remarks,
            ]);

            // send credentials if we created a user
            if ($userAttrs !== null) {
                $user = $student->user;
                if ($user) {
                    $user->notify(new \App\Notifications\SendCredentialsNotification($password));
                }
            }
        });
    }

    public function reject(AdmissionApplication $application, string $reason, ?User $actor = null): void
    {
        $application->update([
            'status' => 'rejected',
            'remarks' => $reason,
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
        ]);
    }
}

