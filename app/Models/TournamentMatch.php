<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatch extends Model
{
    protected $fillable = [
        'tournament_id', 'bracket_round', 'match_number',
        'player1_id', 'player2_id', 'winner_id', 'game_id',
        'status',
    ];

    protected $casts = [
        'bracket_round' => 'integer',
        'match_number' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
