<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyChallenge extends Model
{
    protected $fillable = [
        'date', 'title', 'description', 'criteria', 'reward_xp', 'is_manual', 'addon_id',
        'sim_runs', 'sim_success_rate', 'sim_avg_months', 'sim_computed_at',
    ];

    protected $casts = [
        'criteria' => 'array',
        'date' => 'date',
        'is_manual' => 'boolean',
        'sim_runs' => 'integer',
        'sim_success_rate' => 'integer',
        'sim_avg_months' => 'float',
        'sim_computed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(DailyChallengeEntry::class);
    }
}
