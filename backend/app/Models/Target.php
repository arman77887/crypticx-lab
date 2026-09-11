<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Target extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'url',
        'hostname',
        'scheme',
        'port',
        'authorization_confirmed',
        'authorization_confirmed_at',
        'authorization_method',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'authorization_confirmed' => 'boolean',
            'authorization_confirmed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
