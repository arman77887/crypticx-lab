<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuotaOverride extends Model
{
    use HasUuids;

    protected $fillable = [
        'targets_total',
        'assessments_monthly',
        'reports_monthly',
        'monitoring_policies',
        'concurrent_assessments',
    ];

    protected function casts(): array
    {
        return [
            'targets_total' => 'integer',
            'assessments_monthly' => 'integer',
            'reports_monthly' => 'integer',
            'monitoring_policies' => 'integer',
            'concurrent_assessments' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
