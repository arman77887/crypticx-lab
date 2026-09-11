<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringNotificationPreference extends Model
{
    use HasUuids;

    public const EVENT_TYPES = [
        'finding_new',
        'finding_reappeared',
        'finding_no_longer_detected',
        'finding_reopened',
        'risk_changed',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'email_enabled',
        'event_types',
        'minimum_risk_delta',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'event_types' => 'array',
            'minimum_risk_delta' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function defaultEventTypes(): array
    {
        return self::EVENT_TYPES;
    }
}
