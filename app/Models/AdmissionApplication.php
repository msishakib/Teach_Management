<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_no',
        'name',
        'dob',
        'gender',
        'phone',
        'email',
        'address',
        'guardian_name',
        'guardian_phone',
        'course_id',
        'batch_id',
        'photo',
        'documents',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'dob' => 'date',
        'documents' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(\App\Models\Course::class);
    }

    public function batch()
    {
        return $this->belongsTo(\App\Models\Batch::class);
    }
}

