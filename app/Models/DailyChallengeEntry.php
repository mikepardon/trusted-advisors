<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyChallengeEntry extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_QUIT = 'quit';

    protected $fillable = ['user_id', 'daily_challenge_id', 'game_id', 'completed_at', 'status', 'started_at', 'rounds_taken'];

    protected $casts = [
        'completed_at' => 'datetime',
        'started_at' => 'datetime',
        'rounds_taken' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyChallenge(): BelongsTo
    {
        return $this->belongsTo(DailyChallenge::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
