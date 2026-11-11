<?php

namespace App\Models;

use App\Models\Concerns\ConvertsImagesToWebp;
use App\Models\Concerns\FlushesHomepageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    use ConvertsImagesToWebp, FlushesHomepageCache;

    /** @var list<string> */
    protected array $webpColumns = ['photo'];

    protected $fillable = [
        'teacher_id',
        'subject',
        'qualification',
        'bio',
        'photo',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
