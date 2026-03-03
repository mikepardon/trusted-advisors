<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotatingEventEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'rotating_event_id',
        'user_id',
        'game_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(RotatingEvent::class, 'rotating_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
