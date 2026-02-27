<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'status', 'game_mode', 'num_players', 'current_round', 'total_rounds', 'round_phase', 'win',
        'wealth', 'influence', 'security', 'religion', 'food', 'happiness',
        'user_id',
    ];

    protected $casts = [
        'win' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function cardDeck(): HasMany
    {
        return $this->hasMany(GameCardDeck::class);
    }

    public function itemDeck(): HasMany
    {
        return $this->hasMany(GameItemDeck::class);
    }

    public function playerHands(): HasMany
    {
        return $this->hasMany(GamePlayerHand::class);
    }

    public function roundResults(): HasMany
    {
        return $this->hasMany(GameRoundResult::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(GameInvite::class);
    }

    public function isOnline(): bool
    {
        return $this->game_mode === 'online';
    }

    public function isPassAndPlay(): bool
    {
        return $this->game_mode === 'pass_and_play';
    }

    public function isSinglePlayer(): bool
    {
        return $this->game_mode === 'single';
    }

    public function currentCard()
    {
        return $this->cardDeck()
            ->where('is_drawn', false)
            ->orderBy('position')
            ->first();
    }

    /**
     * Check if any stat has hit 0 (game over).
     * Returns null if all stats are safe, or a string describing the failure.
     */
    public function checkStatBounds(): ?string
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

        foreach ($stats as $stat) {
            if ($this->{$stat} <= 0) {
                return ucfirst($stat) . ' has collapsed to zero!';
            }
        }

        return null;
    }

    /**
     * Check if all players have assigned roles (positive/negative) to their cards.
     */
    public function allPlayersHaveAssignedRoles(): bool
    {
        $expectedCount = $this->num_players * 2;
        $assignedCount = $this->playerHands()
            ->where('round_number', $this->current_round)
            ->whereNotNull('role')
            ->count();

        return $assignedCount >= $expectedCount;
    }

    /**
     * Number of cards needed per round (2 per player).
     */
    public function cardsNeededPerRound(): int
    {
        return $this->num_players * 2;
    }
}
