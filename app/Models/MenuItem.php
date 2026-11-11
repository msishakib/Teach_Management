<?php

namespace App\Models;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Models\Concerns\HasLocalizedColumns;
use App\Services\MenuService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasLocalizedColumns;

    protected $fillable = [
        'location',
        'label',
        'label_bn',
        'link_type',
        'target',
        'open_in_new_tab',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'link_type' => MenuLinkType::class,
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = static fn () => app(MenuService::class)->flush();

        static::saved($flush);
        static::deleted($flush);
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
