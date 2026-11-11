<?php

namespace App\Models;

use App\Models\Concerns\FlushesHomepageCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultVersion extends Model
{
    use FlushesHomepageCache, HasFactory;

    protected $fillable = [
        'exam_id',
        'version',
        'processed_by',
        'processed_at',
        'snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'processed_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }
}
