<?php

namespace App\Models;

use App\Models\Concerns\LogsActivityWithContext;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use LogsActivityWithContext;
    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
