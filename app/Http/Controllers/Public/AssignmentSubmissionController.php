<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Services\Lms\AssignmentSubmissionService;
use App\Services\Lms\CourseAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssignmentSubmissionController extends Controller
{
    public function show(
        Request $request,
        Course $course,
        Assignment $assignment,
        CourseAccessService $access,
    ): View {
        abort_unless((int) $assignment->course_id === (int) $course->id, 404);
        Gate::authorize('submit', $assignment);
        abort_unless($access->canAccessCourse($request->user(), $course), 403);

        $student = $request->user()->student;
        abort_unless($student !== null, 403);

        $submission = AssignmentSubmission::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();

        return view('site.courses.assignment', [
            'course' => $course,
            'assignment' => $assignment,
            'submission' => $submission,
            'isLate' => $assignment->isPastDue(),
            'canSubmit' => $assignment->acceptsSubmissions()
                && ($submission === null || $submission->status->value !== 'graded'),
        ]);
    }

    public function store(
        Request $request,
        Course $course,
        Assignment $assignment,
        AssignmentSubmissionService $submissions,
    ): RedirectResponse {
        abort_unless((int) $assignment->course_id === (int) $course->id, 404);
        Gate::authorize('submit', $assignment);

        $student = $request->user()->student;
        abort_unless($student !== null, 403);

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:20000'],
            'files' => ['nullable', 'array', 'max:'.$assignment->max_files],
            'files.*' => ['file', 'max:10240'],
        ]);

        $submissions->submit(
            $assignment,
            $student,
            $data['text'] ?? null,
            $request->file('files', []) ?: [],
        );

        return redirect()
            ->route('courses.assignments.show', [
                'course' => $course->code,
                'assignment' => $assignment,
            ])
            ->with('status', __('course.assignment.submitted'));
    }
}
