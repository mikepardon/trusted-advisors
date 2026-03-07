<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCurseDeck extends Model
{
    protected $table = 'game_curse_deck';

    protected $fillable = ['game_id', 'curse_id', 'position', 'is_drawn'];

    protected $casts = [
        'is_drawn' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function curse(): BelongsTo
    {
        return $this->belongsTo(Curse::class);
    }
}
