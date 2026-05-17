<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'endpoint', 'method', 'duration_ms', 'query_count',
        'memory_mb', 'status_code', 'bottleneck', 'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];
}
