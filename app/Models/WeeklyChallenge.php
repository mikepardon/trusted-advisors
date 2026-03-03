<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyChallenge extends Model
{
    protected $fillable = ['week_start', 'week_end', 'title', 'description', 'criteria', 'reward_xp', 'reward_coins', 'is_manual', 'addon_id'];

    protected $casts = [
        'criteria' => 'array',
        'week_start' => 'date',
        'week_end' => 'date',
        'is_manual' => 'boolean',
        'reward_xp' => 'integer',
        'reward_coins' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(WeeklyChallengeEntry::class);
    }
}
