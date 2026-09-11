<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'target_id',
        'assessment_id',
        'title',
        'status',
        'target_snapshot',
        'assessment_snapshot',
        'findings_snapshot',
        'risk_snapshot',
        'intelligence_snapshot',
        'metadata',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'target_snapshot' => 'array',
            'assessment_snapshot' => 'array',
            'findings_snapshot' => 'array',
            'risk_snapshot' => 'array',
            'intelligence_snapshot' => 'array',
            'metadata' => 'array',
            'generated_at' => 'datetime',
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
}
