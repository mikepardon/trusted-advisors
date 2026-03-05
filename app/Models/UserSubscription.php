<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'subscription_id',
        'status',
        'current_period_end',
        'cancel_at_period_end',
        'plan_interval',
        'plan_interval_count',
        'plan_amount_cents',
        'plan_currency',
    ];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'plan_interval_count' => 'integer',
            'plan_amount_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
