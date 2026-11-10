<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScaleRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'grading_scale_id',
        'min_percent',
        'max_percent',
        'letter_grade',
        'grade_point',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'grade_point' => 'decimal:2',
        ];
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }
}
