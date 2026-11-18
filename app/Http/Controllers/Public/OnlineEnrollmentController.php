<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Lms\OnlineEnrollmentService;
use App\Services\Payments\CourseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnlineEnrollmentController extends Controller
{
    public function __invoke(
        Request $request,
        Course $course,
        OnlineEnrollmentService $enrollments,
        CourseOrderService $orders,
    ): RedirectResponse {
        abort_unless($course->is_active && $course->is_published, 404);
        abort_unless($course->hasOnlineComponent(), 404);

        $amount = $orders->serverPrice($course);

        if (bccomp($amount, '0', 2) > 0) {
            return redirect()
                ->route('courses.checkout', ['course' => $course->code]);
        }

        $enrollments->enroll($request->user(), $course);

        return redirect()
            ->route('courses.learn', ['course' => $course->code])
            ->with('status', __('course.enroll.success'));
    }
}
