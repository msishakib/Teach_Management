<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\AttendanceResource;
use App\Models\StudentAttendance;
use App\Support\DateRange;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()?->student;

        abort_unless($student !== null, 403, 'Only students can view attendance.');

        $attendance = StudentAttendance::query()
            ->with(['session.batch.course', 'session.subject'])
            ->where('student_id', $student->id)
            ->when($request->filled('from'), fn ($query) => $query->whereHas(
                'session',
                fn ($session) => $session->where('date', '>=', $request->date('from')),
            ))
            ->when($request->filled('to'), fn ($query) => $query->whereHas(
                'session',
                fn ($session) => $session->where('date', '<', DateRange::endExclusive($request->date('to'))),
            ))
            ->latest('id')
            ->paginate(50);

        return AttendanceResource::collection($attendance);
    }
}
