<?php

namespace App\Services;

use App\Enums\ExamResultStatus;
use App\Enums\ExamStatus;
use App\Enums\FeeFrequency;
use App\Enums\NoticeAudience;
use App\Enums\StudentStatus;
use App\Models\Banner;
use App\Models\Course;
use App\Models\ExamResult;
use App\Models\Faq;
use App\Models\Notice;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherProfile;
use App\Models\Testimonial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Builds (and caches) everything the public homepage needs.
 *
 * Courses, teachers, toppers, notices and stats are stored as plain arrays so the
 * cached payload stays small and view rendering never triggers lazy loading.
 */
class HomepageService
{
    public const CACHE_KEY = 'public.homepage.v2';

    public const SITEMAP_KEY = 'public.sitemap.v2';

    public const TTL_MINUTES = 10;

    public function __construct(
        protected SettingsService $settings,
    ) {}

    /**
     * @return array{
     *     banners: Collection,
     *     courses: Collection,
     *     categories: Collection,
     *     teachers: Collection,
     *     toppers: Collection,
     *     testimonials: Collection,
     *     faqs: Collection,
     *     notices: Collection,
     *     stats: array<string, int|null>,
     *     built_at: \Illuminate\Support\Carbon,
     * }
     */
    public function data(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->build(),
        );
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::SITEMAP_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $courses = $this->courses();

        return [
            'banners' => Banner::query()->active()->ordered()->get(),
            'courses' => $courses,
            'categories' => $courses->pluck('category')->filter()->unique()->values(),
            'teachers' => $this->teachers(),
            'toppers' => $this->toppers(),
            'testimonials' => Testimonial::query()->active()->ordered()->limit(12)->get(),
            'faqs' => Faq::query()->active()->ordered()->limit(12)->get(),
            'notices' => $this->notices(),
            'stats' => $this->stats($courses->count()),
            'built_at' => now(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function courses(): Collection
    {
        return Course::query()
            ->where('is_active', true)
            ->with(['profile', 'feeStructures'])
            ->get()
            ->sortBy([
                fn (Course $a, Course $b) => ($a->profile?->sort_order ?? PHP_INT_MAX) <=> ($b->profile?->sort_order ?? PHP_INT_MAX),
                fn (Course $a, Course $b) => $a->name <=> $b->name,
            ])
            ->take(12)
            ->map(function (Course $course): array {
                [$price, $period] = $this->priceFor($course);

                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'description' => $course->description,
                    'tagline' => $course->profile?->tagline,
                    'category' => $course->profile?->category,
                    'duration' => $course->profile?->duration,
                    'image' => $course->profile?->image,
                    'featured' => (bool) $course->profile?->is_featured,
                    'price' => $price,
                    'price_period' => $period,
                ];
            })
            ->values();
    }

    /**
     * Profile price wins; otherwise fall back to the course fee structure.
     *
     * @return array{0: float|null, 1: string|null}
     */
    protected function priceFor(Course $course): array
    {
        if ($course->profile?->price !== null) {
            return [(float) $course->profile->price, $course->profile->price_period ?? FeeFrequency::Monthly->value];
        }

        $monthly = $course->feeStructures->filter(
            fn ($fee) => $this->frequencyValue($fee->frequency) === FeeFrequency::Monthly->value
        );

        if ($monthly->isNotEmpty()) {
            return [(float) $monthly->sum('amount'), FeeFrequency::Monthly->value];
        }

        if ($course->feeStructures->isNotEmpty()) {
            return [(float) $course->feeStructures->sum('amount'), FeeFrequency::OneTime->value];
        }

        return [null, null];
    }

    protected function frequencyValue(mixed $frequency): string
    {
        return $frequency instanceof FeeFrequency ? $frequency->value : (string) $frequency;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function teachers(): Collection
    {
        return TeacherProfile::query()
            ->where('is_featured', true)
            ->whereHas('teacher')
            ->with('teacher')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->map(fn (TeacherProfile $profile): array => [
                'id' => $profile->teacher_id,
                'name' => $profile->teacher->name,
                'subject' => $profile->subject,
                'qualification' => $profile->qualification,
                'bio' => $profile->bio,
                'photo' => $profile->photo,
            ]);
    }

    /**
     * Top-3 ranked, published passes from published exams only.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function toppers(): Collection
    {
        return ExamResult::query()
            ->withoutGlobalScopes()
            ->whereNotNull('published_at')
            ->where('status', ExamResultStatus::Pass->value)
            ->whereBetween('rank_in_batch', [1, 3])
            ->whereHas('exam', fn ($query) => $query->whereIn('status', [
                ExamStatus::Published->value,
                ExamStatus::Closed->value,
            ]))
            ->whereHas('student')
            ->with(['student', 'exam.batch.course'])
            ->orderByDesc('published_at')
            ->orderBy('rank_in_batch')
            ->orderByDesc('percentage')
            ->limit(12)
            ->get()
            ->map(fn (ExamResult $result): array => [
                'name' => $result->student->name,
                'photo' => $result->student->photo,
                'rank' => (int) $result->rank_in_batch,
                'gpa' => (float) $result->gpa,
                'percentage' => (float) $result->percentage,
                'grade' => $result->letter_grade,
                'exam' => $result->exam->name,
                'course' => $result->exam->batch?->course?->name,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function notices(): Collection
    {
        return Notice::query()
            ->published()
            ->where('audience', NoticeAudience::All->value)
            ->latest('published_at')
            ->limit(8)
            ->get(['id', 'title', 'body', 'published_at'])
            ->map(fn (Notice $notice): array => [
                'id' => $notice->id,
                'title' => $notice->title,
                'slug' => $notice->slug(),
                'excerpt' => Str::limit(trim(strip_tags($notice->body)), 130),
                'published_at' => $notice->published_at,
            ]);
    }

    /**
     * Live counters, overridable from Website settings.
     *
     * @return array{students: int, teachers: int, courses: int, success_rate: int|null}
     */
    protected function stats(int $courseCount): array
    {
        $results = ExamResult::query()
            ->withoutGlobalScopes()
            ->whereNotNull('published_at')
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as passed', [ExamResultStatus::Pass->value])
            ->first();

        $successRate = ($results && (int) $results->total > 0)
            ? (int) round(((int) $results->passed / (int) $results->total) * 100)
            : null;

        return [
            'students' => $this->override('stat_students', Student::query()->where('status', StudentStatus::Active->value)->count()),
            'teachers' => $this->override('stat_teachers', Teacher::query()->count()),
            'courses' => $this->override('stat_courses', Course::query()->where('is_active', true)->count() ?: $courseCount),
            'success_rate' => $this->override('stat_success_rate', $successRate, 100),
        ];
    }

    protected function override(string $key, ?int $live, ?int $max = null): ?int
    {
        $value = $this->settings->get("website.{$key}");

        if (is_numeric($value) && (int) $value >= 0) {
            $value = (int) $value;

            return $max !== null ? min($value, $max) : $value;
        }

        return $live;
    }
}
