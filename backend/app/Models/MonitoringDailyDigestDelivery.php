<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringDailyDigestDelivery extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'digest_date',
        'timezone',
        'recipient',
        'status',
        'payload',
        'attempt_count',
        'processing_at',
        'sent_at',
        'failed_at',
        'failure_class',
    ];

    protected function casts(): array
    {
        return [
            'digest_date' => 'date',
            'payload' => 'array',
            'attempt_count' => 'integer',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
