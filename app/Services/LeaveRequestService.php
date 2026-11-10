<?php

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(
        protected AttendanceService $attendance,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LeaveRequest
    {
        return LeaveRequest::query()->create([
            ...$attributes,
            'status' => LeaveRequestStatus::Pending,
            'approved_by' => null,
        ]);
    }

    public function approve(LeaveRequest $leave, User $approver): LeaveRequest
    {
        if ($leave->status !== LeaveRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending leave requests can be approved.',
            ]);
        }

        return DB::transaction(function () use ($leave, $approver) {
            $leave->update([
                'status' => LeaveRequestStatus::Approved,
                'approved_by' => $approver->id,
            ]);

            $this->attendance->applyApprovedLeave($leave->fresh() ?? $leave);

            return $leave->refresh();
        });
    }

    public function reject(LeaveRequest $leave, User $approver): LeaveRequest
    {
        if ($leave->status !== LeaveRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending leave requests can be rejected.',
            ]);
        }

        $leave->update([
            'status' => LeaveRequestStatus::Rejected,
            'approved_by' => $approver->id,
        ]);

        return $leave->refresh();
    }
}
