<?php

namespace App\Services\Lms;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Scopes\PortalStudentOwnedScope;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for course/lesson access.
 *
 * Rules:
 *  - staff with course/media permissions may always view;
 *  - free-preview lessons are public (but only when published);
 *  - learners need an active, unexpired enrollment on the course;
 *  - optional per-course access_days and per-lesson drip_after_days are honoured.
 */
class CourseAccessService
{
    /** @var array<int, int|null> */
    protected array $courseAccessDays = [];

    public function canView(?User $user, Lesson $lesson): bool
    {
        if (! $lesson->isPublished()) {
            return $user !== null && $this->isStaff($user);
        }

        if ($lesson->is_free_preview) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($this->isStaff($user)) {
            return true;
        }

        return $this->hasCourseAccess($user, (int) $lesson->course_id, $lesson);
    }

    public function canAccessCourse(?User $user, Course|int $course): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isStaff($user)) {
            return true;
        }

        $courseId = $course instanceof Course ? (int) $course->getKey() : $course;

        return $this->hasCourseAccess($user, $courseId);
    }

    public function canViewResource(?User $user, LessonResource $resource): bool
    {
        $lesson = $resource->relationLoaded('lesson')
            ? $resource->lesson
            : $resource->lesson()->first();

        return $lesson instanceof Lesson && $this->canView($user, $lesson);
    }

    public function isStaff(User $user): bool
    {
        $isPortalLearner = $user->hasRole(RoleName::Student->value)
            || $user->hasRole(RoleName::Guardian->value);

        return ! $isPortalLearner
            && ($user->can('media.view_private') || $user->can('course.view'));
    }

    /**
     * Drip-locked lessons for the player sidebar. Expects [lessonId => days].
     *
     * @param  array<int, int|null>  $dripDaysByLesson
     * @return list<int>
     */
    public function dripLockedLessonIds(User $user, int $courseId, array $dripDaysByLesson): array
    {
        $enrollments = $this->accessEnrollments($user, $courseId)
            ->reject(fn (Enrollment $enrollment): bool => $this->enrollmentExpired($enrollment, $courseId));

        $locked = [];

        foreach ($dripDaysByLesson as $lessonId => $days) {
            if ($days === null) {
                continue;
            }

            $available = $enrollments->contains(
                fn (Enrollment $enrollment): bool => ! $this->dripLocked($enrollment, (int) $days),
            );

            if (! $available) {
                $locked[] = (int) $lessonId;
            }
        }

        return $locked;
    }

    public function accessStartFor(?User $user, Course|int $course): ?Carbon
    {
        if ($user === null) {
            return null;
        }

        $courseId = $course instanceof Course ? (int) $course->getKey() : $course;

        $enrollment = $this->accessEnrollments($user, $courseId)
            ->first(fn (Enrollment $enrollment): bool => ! $this->enrollmentExpired($enrollment, $courseId));

        return $enrollment !== null ? $this->accessStart($enrollment) : null;
    }

    protected function hasCourseAccess(User $user, int $courseId, ?Lesson $lesson = null): bool
    {
        $enrollments = $this->accessEnrollments($user, $courseId);

        foreach ($enrollments as $enrollment) {
            if ($this->enrollmentExpired($enrollment, $courseId)) {
                continue;
            }

            if ($lesson !== null && $this->dripLocked($enrollment, $lesson->drip_after_days)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return Collection<int, Enrollment>
     */
    protected function accessEnrollments(User $user, int $courseId): Collection
    {
        $studentIds = $this->studentIdsFor($user);

        if ($studentIds === []) {
            return collect();
        }

        return Enrollment::query()
            ->withoutGlobalScope(PortalStudentOwnedScope::class)
            ->whereIn('student_id', $studentIds)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('batch', fn ($query) => $query->where('course_id', $courseId))
            ->get();
    }

    protected function enrollmentExpired(Enrollment $enrollment, int $courseId): bool
    {
        if ($enrollment->expires_at !== null && $enrollment->expires_at->isPast()) {
            return true;
        }

        $days = $this->courseAccessDays($courseId);
        $start = $this->accessStart($enrollment);

        return $days !== null
            && $start !== null
            && $start->copy()->addDays($days)->isPast();
    }

    protected function dripLocked(Enrollment $enrollment, ?int $days): bool
    {
        if ($days === null) {
            return false;
        }

        $start = $this->accessStart($enrollment);

        return $start !== null && now()->lt($start->copy()->addDays($days));
    }

    protected function accessStart(Enrollment $enrollment): ?Carbon
    {
        $start = $enrollment->enrolled_on ?? $enrollment->created_at;

        return $start !== null ? Carbon::parse($start)->startOfDay() : null;
    }

    protected function courseAccessDays(int $courseId): ?int
    {
        if (! array_key_exists($courseId, $this->courseAccessDays)) {
            $days = Course::query()->whereKey($courseId)->value('access_days');
            $this->courseAccessDays[$courseId] = $days !== null ? (int) $days : null;
        }

        return $this->courseAccessDays[$courseId];
    }

    /**
     * @return list<int>
     */
    protected function studentIdsFor(User $user): array
    {
        $user->loadMissing(['student', 'guardian']);

        $ids = [];

        if ($user->student) {
            $ids[] = (int) $user->student->id;
        }

        if ($user->guardian && method_exists($user->guardian, 'students')) {
            $ids = array_merge($ids, $user->guardian->students()->pluck('students.id')->all());
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
