<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'assessment_id',
        'target_id',
        'type',
        'fingerprint',
        'title',
        'description',
        'severity',
        'confidence',
        'evidence',
        'evidence_data',
        'remediation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'evidence_data' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }
}
