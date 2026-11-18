<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\MediaCategory;
use App\Models\Concerns\FlushesHomepageCache;
use App\Models\Concerns\HasManagedMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;

class Course extends Model implements HasMedia
{
    use FlushesHomepageCache, HasFactory, HasManagedMedia, SoftDeletes;

    public const CURRICULUM_CACHE_TTL_MINUTES = 10;

    /**
     * @return list<MediaCategory>
     */
    public function mediaCategories(): array
    {
        return [MediaCategory::CourseThumbnail];
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'delivery_mode',
        'is_published',
        'price',
        'sale_price',
        'level',
        'language',
        'short_description',
        'requirements',
        'what_you_learn',
        'access_days',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'delivery_mode' => DeliveryMode::class,
            'is_published' => 'boolean',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'what_you_learn' => 'array',
            'access_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $flush = static fn (self $course) => static::flushCurriculumCache((int) $course->getKey());

        static::saved($flush);
        static::deleted($flush);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CourseProfile::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)
            ->orderBy('sort_order')
            ->orderBy('order')
            ->orderBy('id');
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function requiresPayment(): bool
    {
        $price = $this->effectivePrice();

        return $price !== null && (float) $price > 0;
    }

    public function completions(): HasMany
    {
        return $this->hasMany(CourseCompletion::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeWithOnlineDelivery(Builder $query): Builder
    {
        return $query->whereIn('delivery_mode', [
            DeliveryMode::Online->value,
            DeliveryMode::Hybrid->value,
        ]);
    }

    public function hasOnlineComponent(): bool
    {
        return $this->delivery_mode?->hasOnlineComponent() ?? false;
    }

    /**
     * The sale price when it is set (and cheaper), otherwise the regular price.
     */
    public function effectivePrice(): ?float
    {
        if ($this->sale_price !== null && (float) $this->sale_price > 0) {
            return (float) $this->sale_price;
        }

        return $this->price !== null ? (float) $this->price : null;
    }

    public function hasDiscount(): bool
    {
        return $this->sale_price !== null
            && $this->price !== null
            && (float) $this->sale_price < (float) $this->price;
    }

    /**
     * Published lesson count and total duration, cached for the public pages.
     *
     * @return array{lessons: int, duration: int}
     */
    public function curriculumStats(): array
    {
        return Cache::remember(
            $this->curriculumStatsCacheKey(),
            now()->addMinutes(self::CURRICULUM_CACHE_TTL_MINUTES),
            function (): array {
                $query = $this->lessons()->where('is_published', true);

                return [
                    'lessons' => (clone $query)->count(),
                    // Older lessons only have the legacy `duration` column.
                    'duration' => (int) ((clone $query)
                        ->selectRaw('SUM(COALESCE(duration_seconds, duration, 0)) as total')
                        ->first()?->total ?? 0),
                ];
            },
        );
    }

    public function totalLessons(): int
    {
        return $this->curriculumStats()['lessons'];
    }

    public function totalDurationSeconds(): int
    {
        return $this->curriculumStats()['duration'];
    }

    public function curriculumCacheKey(): string
    {
        return "course.{$this->getKey()}.curriculum.public.v1";
    }

    public function curriculumStatsCacheKey(): string
    {
        return "course.{$this->getKey()}.curriculum.stats.v1";
    }

    public static function flushCurriculumCache(?int $courseId): void
    {
        if ($courseId === null) {
            return;
        }

        Cache::forget("course.{$courseId}.curriculum.public.v1");
        Cache::forget("course.{$courseId}.curriculum.stats.v1");
    }
}
