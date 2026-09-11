<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkerNode extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'worker_type',
        'hostname',
        'pid',
        'connection',
        'queue',
        'status',
        'started_at',
        'last_heartbeat_at',
        'stopped_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'pid' => 'integer',
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'stopped_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
