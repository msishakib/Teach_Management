<?php

namespace App\Services\Lms;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the public curriculum as plain arrays so it can be cached safely and
 * rendered without triggering lazy loading or N+1 queries.
 */
class CourseCurriculumService
{
    public const TTL_MINUTES = Course::CURRICULUM_CACHE_TTL_MINUTES;

    /**
     * @return list<array{
     *     id: int|null,
     *     title: string,
     *     sort_order: int,
     *     lessons: list<array<string, mixed>>
     * }>
     */
    public function publicCurriculum(Course $course): array
    {
        return Cache::remember(
            $course->curriculumCacheKey(),
            now()->addMinutes(self::TTL_MINUTES),
            fn (): array => $this->build($course),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $curriculum
     * @return list<array<string, mixed>>
     */
    public function flatten(array $curriculum): array
    {
        $lessons = [];

        foreach ($curriculum as $section) {
            foreach ($section['lessons'] as $lesson) {
                $lessons[] = $lesson;
            }
        }

        return $lessons;
    }

    /**
     * @param  list<array<string, mixed>>  $curriculum
     */
    public function findLesson(array $curriculum, int $lessonId): ?array
    {
        foreach ($this->flatten($curriculum) as $lesson) {
            if ((int) $lesson['id'] === $lessonId) {
                return $lesson;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *     id: int|null,
     *     title: string,
     *     sort_order: int,
     *     lessons: list<array<string, mixed>>
     * }>
     */
    protected function build(Course $course): array
    {
        $sections = $course->sections()
            ->with([
                'lessons' => fn ($query) => $query
                    ->where('is_published', true)
                    ->orderBy('sort_order')
                    ->orderBy('order')
                    ->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $curriculum = $sections
            ->map(fn (CourseSection $section): array => [
                'id' => (int) $section->getKey(),
                'title' => $section->title,
                'sort_order' => (int) $section->sort_order,
                'lessons' => $section->lessons
                    ->map(fn (Lesson $lesson): array => $this->lessonPayload($lesson))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $looseLessons = $course->lessons()
            ->whereNull('section_id')
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        if ($looseLessons->isNotEmpty()) {
            array_unshift($curriculum, [
                'id' => null,
                'title' => 'Lessons',
                'sort_order' => -1,
                'lessons' => $looseLessons
                    ->map(fn (Lesson $lesson): array => $this->lessonPayload($lesson))
                    ->values()
                    ->all(),
            ]);
        }

        return $curriculum;
    }

    /**
     * @return array<string, mixed>
     */
    protected function lessonPayload(Lesson $lesson): array
    {
        $duration = (int) ($lesson->duration_seconds ?? $lesson->duration ?? 0);

        return [
            'id' => (int) $lesson->getKey(),
            'title' => $lesson->title,
            'type' => $lesson->type?->value ?? 'video',
            'duration_seconds' => $duration,
            'is_free_preview' => (bool) $lesson->is_free_preview,
            'sort_order' => (int) $lesson->sort_order,
            'drip_after_days' => $lesson->drip_after_days !== null ? (int) $lesson->drip_after_days : null,
        ];
    }
}
