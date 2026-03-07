<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayerCurse extends Model
{
    protected $fillable = ['game_player_id', 'curse_id', 'acquired_round'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }

    public function curse(): BelongsTo
    {
        return $this->belongsTo(Curse::class);
    }
}
