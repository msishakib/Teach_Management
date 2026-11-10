<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScale extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(GradingScaleRule::class)->orderByDesc('min_percent');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
