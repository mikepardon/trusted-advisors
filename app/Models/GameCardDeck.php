<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCardDeck extends Model
{
    protected $table = 'game_card_deck';

    protected $fillable = ['game_id', 'card_id', 'position', 'is_drawn'];

    protected $casts = [
        'is_drawn' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
