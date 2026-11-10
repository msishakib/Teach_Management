<?php

namespace App\Models;

use App\Enums\AcademicSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AcademicSessionStatus::class,
            'is_current' => 'boolean',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'session_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
