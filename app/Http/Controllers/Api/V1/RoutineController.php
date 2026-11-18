<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\RoutineResource;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class RoutineController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $student = $user?->student;

        abort_unless($student !== null, 403, 'Only students can view the routine.');

        $batchIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('batch_id');

        $routines = ClassRoutine::query()
            ->with(['batch.course', 'subject', 'teacher'])
            ->whereIn('batch_id', $batchIds)
            ->when($request->filled('day'), fn ($query) => $query->where('day', $request->string('day')))
            ->orderBy('day')
            ->orderBy('start_time')
            ->paginate(50);

        return RoutineResource::collection($routines);
    }
}
