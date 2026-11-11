<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\NoticeAudience;
use App\Enums\RoleName;
use App\Events\NoticePublished;
use App\Models\Concerns\FlushesHomepageCache;
use App\Support\PortalAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Notice extends Model
{
    use FlushesHomepageCache, HasFactory;

    protected $fillable = [
        'title',
        'body',
        'audience',
        'batch_id',
        'attachment',
        'published_at',
        'expires_at',
        'is_pinned',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience' => NoticeAudience::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $notice): void {
            $isPublishedNow = $notice->is_active
                && $notice->published_at !== null
                && $notice->published_at->lte(now());

            if ($isPublishedNow && ($notice->wasRecentlyCreated || $notice->wasChanged(['is_active', 'published_at']))) {
                event(new NoticePublished($notice));
            }
        });
    }

    /**
     * URL slug that keeps Bangla letters and vowel signs (Str::slug strips the signs).
     */
    public static function slugFor(?string $title): ?string
    {
        $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', mb_strtolower((string) $title)) ?? '';
        $slug = trim(mb_substr(trim($slug, '-'), 0, 80), '-');

        return $slug === '' ? null : $slug;
    }

    public function slug(): ?string
    {
        return static::slugFor($this->title);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        $query
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if (Schema::hasColumn('notices', 'expires_at')) {
            $query->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
        }

        return $query;
    }

    public function scopeVisibleToPortalUser(Builder $query, ?User $user = null): Builder
    {
        $user ??= PortalAccess::user();

        if (! $user || PortalAccess::isStaff($user)) {
            return $query;
        }

        if (PortalAccess::isTeacher($user)) {
            $batchIds = $user->teacher?->assignedBatchIds() ?? [];

            return $query->where(function (Builder $inner) use ($batchIds) {
                $inner->whereIn('audience', [
                    NoticeAudience::All->value,
                    NoticeAudience::Teachers->value,
                ]);

                if ($batchIds !== []) {
                    $inner->orWhere(function (Builder $batchQuery) use ($batchIds) {
                        $batchQuery->where('audience', NoticeAudience::Batch->value)
                            ->whereIn('batch_id', $batchIds);
                    });
                }
            });
        }

        $studentIds = PortalAccess::accessibleStudentIds($user) ?? [];
        $isGuardian = (bool) ($user->guardian || $user->hasRole(RoleName::Guardian->value));

        if ($studentIds === [] && ! $isGuardian) {
            return $query->whereRaw('1 = 0');
        }

        $batchIds = $studentIds === []
            ? []
            : Enrollment::query()
                ->whereIn('student_id', $studentIds)
                ->where('status', EnrollmentStatus::Active)
                ->pluck('batch_id')
                ->unique()
                ->all();

        $audiences = [
            NoticeAudience::All->value,
            NoticeAudience::Students->value,
        ];

        if ($isGuardian) {
            $audiences[] = NoticeAudience::Guardians->value;
        }

        return $query->where(function (Builder $inner) use ($audiences, $batchIds) {
            $inner->whereIn('audience', $audiences);

            if ($batchIds !== []) {
                $inner->orWhere(function (Builder $batchQuery) use ($batchIds) {
                    $batchQuery->where('audience', NoticeAudience::Batch->value)
                        ->whereIn('batch_id', $batchIds);
                });
            }
        });
    }
}
