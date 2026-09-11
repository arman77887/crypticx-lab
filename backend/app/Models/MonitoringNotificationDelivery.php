<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringNotificationDelivery extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'change_event_id',
        'channel',
        'status',
        'recipient',
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

    public function changeEvent(): BelongsTo
    {
        return $this->belongsTo(
            MonitoringChangeEvent::class,
            'change_event_id'
        );
    }
}
