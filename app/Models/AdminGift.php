<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminGift extends Model
{
    protected $fillable = [
        'title', 'note', 'reward_xp', 'reward_coins',
        'reward_character_id', 'reward_dice_theme_id', 'reward_kingdom_style_id',
        'created_by', 'recipient_count',
        'target_type', 'target_user_ids', 'target_criteria',
        'status', 'scheduled_at',
    ];

    protected $casts = [
        'reward_xp' => 'integer',
        'reward_coins' => 'integer',
        'recipient_count' => 'integer',
        'target_user_ids' => 'array',
        'target_criteria' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
