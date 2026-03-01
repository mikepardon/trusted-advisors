<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRoundResult extends Model
{
    protected $fillable = [
        'game_id', 'round_number', 'card_id', 'game_player_id',
        'success', 'dice_results', 'stat_totals', 'required', 'effects_applied',
        'result_type', 'cards_included', 'wild_triggers',
        'special_effects', 'kingdom_snapshot', 'event_data',
    ];

    protected $casts = [
        'success' => 'boolean',
        'dice_results' => 'array',
        'stat_totals' => 'array',
        'required' => 'array',
        'effects_applied' => 'array',
        'cards_included' => 'array',
        'wild_triggers' => 'array',
        'special_effects' => 'array',
        'kingdom_snapshot' => 'array',
        'event_data' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }
}
