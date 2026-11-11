<?php

namespace App\Models\Concerns;

use App\Services\HomepageService;

/**
 * Drops the cached public homepage payload whenever the model changes.
 */
trait FlushesHomepageCache
{
    protected static function bootFlushesHomepageCache(): void
    {
        $flush = static fn () => app(HomepageService::class)->flush();

        static::saved($flush);
        static::deleted($flush);

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored($flush);
        }
    }
}
