<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayer extends Model
{
    protected $fillable = ['game_id', 'user_id', 'character_id', 'player_number', 'lost_dice', 'is_bot', 'bot_difficulty', 'ability_uses', 'ability_active_this_round', 'item_decided'];

    protected $casts = [
        'lost_dice' => 'integer',
        'is_bot' => 'boolean',
        'ability_uses' => 'integer',
        'ability_active_this_round' => 'boolean',
        'item_decided' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GamePlayerItem::class);
    }

    public function kingdom(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GamePlayerKingdom::class);
    }
}
