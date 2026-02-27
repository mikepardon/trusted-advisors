<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameItemDeck extends Model
{
    protected $table = 'game_item_deck';

    protected $fillable = ['game_id', 'item_id', 'position', 'is_drawn'];

    protected $casts = [
        'is_drawn' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
