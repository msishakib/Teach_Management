<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class Guardian extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
    ];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->withPivot('relation')
            ->withTimestamps();
    }
}
