<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchmakingEntry extends Model
{
    protected $fillable = ['user_id', 'elo_rating', 'elo_range', 'total_rounds', 'status', 'matched_game_id', 'bot_timeout'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchedGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'matched_game_id');
    }
}
