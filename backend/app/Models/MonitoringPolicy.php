<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'target_id',
        'enabled',
        'profile',
        'interval_minutes',
        'configuration',
        'last_scheduled_at',
        'next_run_at',
        'last_assessment_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'interval_minutes' => 'integer',
            'configuration' => 'array',
            'last_scheduled_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function lastAssessment(): BelongsTo
    {
        return $this->belongsTo(
            Assessment::class,
            'last_assessment_id'
        );
    }
}
