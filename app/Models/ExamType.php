<?php

namespace App\Models;

use App\Enums\ExamTypeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'weight',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'status' => ExamTypeStatus::class,
        ];
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
