<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyChallenge extends Model
{
    protected $fillable = ['date', 'title', 'description', 'criteria', 'reward_xp', 'is_manual', 'addon_id'];

    protected $casts = [
        'criteria' => 'array',
        'date' => 'date',
        'is_manual' => 'boolean',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(DailyChallengeEntry::class);
    }
}
