<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyChallengeEntry extends Model
{
    protected $fillable = ['user_id', 'weekly_challenge_id', 'game_id', 'progress', 'completed_at'];

    protected $casts = [
        'progress' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function weeklyChallenge(): BelongsTo
    {
        return $this->belongsTo(WeeklyChallenge::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
