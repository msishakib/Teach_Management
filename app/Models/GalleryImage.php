<?php

namespace App\Models;

use App\Enums\MediaCategory;
use App\Models\Concerns\ConvertsImagesToWebp;
use App\Models\Concerns\FlushesHomepageCache;
use App\Models\Concerns\HasLocalizedColumns;
use App\Models\Concerns\HasManagedMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

class GalleryImage extends Model implements HasMedia
{
    use ConvertsImagesToWebp, FlushesHomepageCache, HasLocalizedColumns, HasManagedMedia;

    /**
     * @return list<MediaCategory>
     */
    public function mediaCategories(): array
    {
        return [MediaCategory::Gallery];
    }

    /** @var list<string> */
    protected array $webpColumns = ['image'];

    protected $fillable = [
        'title',
        'title_bn',
        'image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
