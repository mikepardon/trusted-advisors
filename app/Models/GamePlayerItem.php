<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayerItem extends Model
{
    protected $fillable = ['game_player_id', 'item_id', 'is_used', 'acquired_round', 'is_cursed'];

    protected $casts = [
        'is_used' => 'boolean',
        'is_cursed' => 'boolean',
    ];

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
