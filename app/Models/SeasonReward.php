<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonReward extends Model
{
    protected $fillable = [
        'season_id',
        'metric',
        'placement',
        'reward_xp',
        'reward_coins',
        'reward_character_id',
        'reward_dice_theme_id',
        'reward_kingdom_style_id',
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

    public function rewardDiceTheme(): BelongsTo
    {
        return $this->belongsTo(DiceTheme::class, 'reward_dice_theme_id');
    }

    public function rewardKingdomStyle(): BelongsTo
    {
        return $this->belongsTo(KingdomStyle::class, 'reward_kingdom_style_id');
    }
}
