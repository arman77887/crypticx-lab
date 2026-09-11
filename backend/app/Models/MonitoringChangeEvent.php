<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringChangeEvent extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'target_id',
        'assessment_id',
        'previous_assessment_id',
        'event_type',
        'fingerprint',
        'payload',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'detected_at' => 'datetime',
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

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function previousAssessment(): BelongsTo
    {
        return $this->belongsTo(
            Assessment::class,
            'previous_assessment_id'
        );
    }
}
