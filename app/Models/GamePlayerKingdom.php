<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayerKingdom extends Model
{
    protected $fillable = [
        'game_id', 'game_player_id',
        'wealth', 'influence', 'security', 'religion', 'food', 'happiness',
    ];

    protected $casts = [
        'wealth' => 'integer',
        'influence' => 'integer',
        'security' => 'integer',
        'religion' => 'integer',
        'food' => 'integer',
        'happiness' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }

    public function totalScore(): int
    {
        return $this->wealth + $this->influence + $this->security
            + $this->religion + $this->food + $this->happiness;
    }

    public function applyEffects(array $effects): void
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

        foreach ($stats as $stat) {
            if (isset($effects[$stat])) {
                $this->{$stat} = max(0, min(20, $this->{$stat} + $effects[$stat]));
            }
        }

        $this->save();
    }
}
