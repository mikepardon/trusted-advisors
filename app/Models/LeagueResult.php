<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueResult extends Model
{
    protected $fillable = [
        'user_id', 'cohort_id', 'week_start', 'tier', 'rank', 'total',
        'tier_before', 'tier_after', 'coins_earned', 'seen_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cohort_id' => 'integer',
            'week_start' => 'immutable_date',
            'tier' => 'integer',
            'rank' => 'integer',
            'total' => 'integer',
            'tier_before' => 'integer',
            'tier_after' => 'integer',
            'coins_earned' => 'integer',
            'seen_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LeagueCohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(LeagueCohort::class, 'cohort_id');
    }
}
