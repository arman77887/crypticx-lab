<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDevice extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'device_token_hash',
        'name',
        'device_type',
        'browser',
        'platform',
        'last_ip_address',
        'first_seen_at',
        'last_seen_at',
        'verified_at',
        'expires_at',
        'is_trusted',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'is_trusted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
