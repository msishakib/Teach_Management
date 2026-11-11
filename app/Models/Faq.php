<?php

namespace App\Models;

use App\Models\Concerns\FlushesHomepageCache;
use App\Models\Concerns\HasLocalizedColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use FlushesHomepageCache, HasLocalizedColumns;

    protected $fillable = [
        'question',
        'question_bn',
        'answer',
        'answer_bn',
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
