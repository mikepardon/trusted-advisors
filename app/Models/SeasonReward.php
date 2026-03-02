<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonReward extends Model
{
    protected $fillable = [
        'season_id',
        'placement',
        'reward_xp',
        'reward_coins',
        'reward_character_id',
        'reward_title',
    ];

    protected $casts = [
        'placement' => 'integer',
        'reward_xp' => 'integer',
        'reward_coins' => 'integer',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function rewardCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'reward_character_id');
    }
}
