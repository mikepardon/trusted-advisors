<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'name', 'creator_id', 'status', 'game_type', 'max_players',
        'total_rounds', 'current_bracket_round', 'custom_rules',
        'is_private', 'lobby_password',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'max_players' => 'integer',
        'total_rounds' => 'integer',
        'current_bracket_round' => 'integer',
        'custom_rules' => 'array',
        'is_private' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }
}
