<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'plan_code',
        'status',
        'provider',
        'provider_customer_id',
        'provider_subscription_id',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'provider_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'provider_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantsEntitlements(): bool
    {
        if (! in_array(
            $this->status,
            [
                self::STATUS_TRIALING,
                self::STATUS_ACTIVE,
            ],
            true
        )) {
            return false;
        }

        /*
         * A known end date is authoritative.
         * Once it has passed, paid entitlements fail closed.
         */
        if (
            $this->current_period_end !== null
            && $this->current_period_end->isPast()
        ) {
            return false;
        }

        return true;
    }
}
