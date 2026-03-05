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
        'user_id', 'season_id', 'rotating_event_id',
        'game_type', 'offerer_player_number', 'duel_phase', 'winner_player_number', 'timed_out_player_number',
        'event_order', 'share_token',
        'bonus_score', 'final_score',
        'turn_time_limit', 'turn_started_at', 'cancelled_at',
        'is_custom', 'custom_rules', 'is_private', 'lobby_password', 'tournament_match_id',
    ];

    protected $casts = [
        'win' => 'boolean',
        'is_custom' => 'boolean',
        'custom_rules' => 'array',
        'is_private' => 'boolean',
        'offerer_player_number' => 'integer',
        'winner_player_number' => 'integer',
        'timed_out_player_number' => 'integer',
        'event_order' => 'array',
        'bonus_score' => 'integer',
        'final_score' => 'integer',
        'turn_time_limit' => 'integer',
        'turn_started_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function rotatingEvent(): BelongsTo
    {
        return $this->belongsTo(RotatingEvent::class);
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

    public function playerKingdoms(): HasMany
    {
        return $this->hasMany(GamePlayerKingdom::class);
    }

    public function tournamentMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class);
    }

    public function isDuel(): bool
    {
        return $this->game_type === 'duel';
    }

    public function isCooperative(): bool
    {
        return $this->game_type !== 'duel';
    }

    public function getOfferer(): ?GamePlayer
    {
        return $this->players()->where('player_number', $this->offerer_player_number)->first();
    }

    public function getChooser(): ?GamePlayer
    {
        $chooserNumber = $this->offerer_player_number === 1 ? 2 : 1;
        return $this->players()->where('player_number', $chooserNumber)->first();
    }

    /**
     * Check if any stat in a duel kingdom has hit bounds.
     * Returns null if safe, or a string describing the condition.
     */
    public function checkDuelStatBounds(GamePlayerKingdom $kingdom): ?string
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $atMax = 0;
        $lossThreshold = 0;

        // Hardcore mode: lose at stat <= 3
        if (!empty($this->custom_rules['house_rules']['hardcore_mode'])) {
            $lossThreshold = 3;
        }

        foreach ($stats as $stat) {
            if ($kingdom->{$stat} <= $lossThreshold) {
                return 'loss';
            }
            if ($kingdom->{$stat} >= 20) {
                $atMax++;
            }
        }

        if ($atMax >= 3) {
            return 'win';
        }

        return null;
    }

    public function generateShareToken(): string
    {
        $this->share_token = bin2hex(random_bytes(16));
        $this->save();

        return $this->share_token;
    }

    public function turnTimeRemaining(): ?int
    {
        if (!$this->turn_time_limit || !$this->turn_started_at) {
            return null;
        }

        $elapsed = (int) $this->turn_started_at->diffInSeconds(now());
        return max(0, $this->turn_time_limit - $elapsed);
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
        $threshold = 0;

        // Hardcore mode: lose at stat <= 3
        if (!empty($this->custom_rules['house_rules']['hardcore_mode'])) {
            $threshold = 3;
        }

        foreach ($stats as $stat) {
            if ($this->{$stat} <= $threshold) {
                $label = ucfirst($stat);
                return $threshold > 0
                    ? "{$label} has fallen to {$this->{$stat}} (hardcore mode)!"
                    : "{$label} has collapsed to zero!";
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

    /**
     * Year multiplier based on the current round reached (12 rounds = 1 year).
     */
    public function yearMultiplier(): float
    {
        $multipliers = [
            1 => 1.0,
            2 => 1.4,
            3 => 1.7,
            4 => 1.9,
            5 => 2.0,
        ];

        $years = max(1, (int) ceil($this->current_round / 12));
        return $multipliers[$years] ?? 2.0;
    }

    /**
     * Number of full years (12 rounds) completed.
     */
    public function yearsCompleted(): int
    {
        return (int) floor($this->current_round / 12);
    }

    /**
     * Year bonus: +50 points per completed year.
     */
    public function yearBonus(): int
    {
        return $this->yearsCompleted() * 50;
    }

    /**
     * Base score: sum of 6 kingdom stats.
     */
    public function baseScore(): int
    {
        return $this->wealth + $this->influence + $this->security
             + $this->religion + $this->food + $this->happiness;
    }

    /**
     * Balance bonus: rewards balanced kingdoms.
     * max(0, 30 - (max_stat - min_stat) * 3)
     */
    public function balanceBonus(): int
    {
        $stats = [$this->wealth, $this->influence, $this->security,
                  $this->religion, $this->food, $this->happiness];
        $spread = max($stats) - min($stats);
        return max(0, 30 - $spread * 3);
    }

    /**
     * Compute and store the final composite score (cooperative only).
     */
    public function computeFinalScore(): int
    {
        $base = $this->baseScore();
        $multiplied = (int) floor($base * $this->yearMultiplier());
        $balance = $this->balanceBonus();
        $yearBonus = $this->yearBonus();
        $bonus = $this->bonus_score ?? 0;

        $this->final_score = $multiplied + $balance + $yearBonus + $bonus;
        return $this->final_score;
    }
}
