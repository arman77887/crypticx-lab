<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'device_token_hash',
        'device_type',
        'browser',
        'platform',
        'first_ip_address',
        'last_ip_address',
        'registered_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = [
        'device_token_hash',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
