<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function classRoutines(): HasMany
    {
        return $this->hasMany(ClassRoutine::class);
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }
}
