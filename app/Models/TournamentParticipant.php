<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentParticipant extends Model
{
    protected $fillable = [
        'tournament_id', 'user_id', 'seed',
        'eliminated_at', 'final_placement',
    ];

    protected $casts = [
        'seed' => 'integer',
        'final_placement' => 'integer',
        'eliminated_at' => 'datetime',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
