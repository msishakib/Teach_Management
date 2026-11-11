<?php

namespace App\Models;

use App\Models\Concerns\ConvertsImagesToWebp;
use App\Models\Concerns\FlushesHomepageCache;
use App\Models\Concerns\HasLocalizedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use ConvertsImagesToWebp, FlushesHomepageCache, HasLocalizedColumns;

    /** @var list<string> */
    protected array $webpColumns = ['photo'];

    protected $fillable = [
        'name',
        'role',
        'quote',
        'quote_bn',
        'photo',
        'rating',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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
