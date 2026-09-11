<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingLifecycle extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'finding_lifecycles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'target_id',
        'fingerprint',
        'type',
        'title',
        'severity',
        'confidence',
        'status',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'reopened_at',
        'occurrence_count',
        'first_assessment_id',
        'last_assessment_id',
        'last_finding_id',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'reopened_at' => 'datetime',
            'occurrence_count' => 'integer',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function firstAssessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'first_assessment_id');
    }

    public function lastAssessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'last_assessment_id');
    }

    public function lastFinding(): BelongsTo
    {
        return $this->belongsTo(Finding::class, 'last_finding_id');
    }
}
