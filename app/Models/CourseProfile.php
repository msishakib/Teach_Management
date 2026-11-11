<?php

namespace App\Models;

use App\Models\Concerns\ConvertsImagesToWebp;
use App\Models\Concerns\FlushesHomepageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseProfile extends Model
{
    use ConvertsImagesToWebp, FlushesHomepageCache;

    /** @var list<string> */
    protected array $webpColumns = ['image'];

    protected $fillable = [
        'course_id',
        'category',
        'tagline',
        'duration',
        'price',
        'price_period',
        'image',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
