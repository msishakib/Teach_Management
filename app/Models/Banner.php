<?php

namespace App\Models;

use App\Models\Concerns\ConvertsImagesToWebp;
use App\Models\Concerns\FlushesHomepageCache;
use App\Models\Concerns\HasLocalizedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use ConvertsImagesToWebp, FlushesHomepageCache, HasLocalizedColumns;

    /** @var list<string> */
    protected array $webpColumns = ['image'];

    protected $fillable = [
        'title',
        'title_bn',
        'subtitle',
        'subtitle_bn',
        'cta_label',
        'cta_label_bn',
        'cta_url',
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
