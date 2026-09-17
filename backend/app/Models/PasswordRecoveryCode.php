<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PasswordRecoveryCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'code_hash',
        'expires_at',
        'attempts',
        'verified_at',
        'reset_token_hash',
        'reset_token_expires_at',
        'used_at',
    ];

    protected $hidden = [
        'code_hash',
        'reset_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
