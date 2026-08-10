<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaravelUpgrade extends Model
{
    protected $fillable = [
        'current_version',
        'target_version',
        'status',
        'output',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
