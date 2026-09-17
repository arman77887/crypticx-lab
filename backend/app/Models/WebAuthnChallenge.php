<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnChallenge extends Model
{
    use HasUuids;

    public const PURPOSE_REGISTER = 'register';
    public const PURPOSE_AUTHENTICATE = 'authenticate';

    protected $table = 'webauthn_challenges';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'purpose',
        'challenge',
        'options_json',
        'expires_at',
        'used_at',
    ];

    protected $hidden = [
        'challenge',
        'options_json',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
