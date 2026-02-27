<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameInvite extends Model
{
    protected $fillable = ['game_id', 'sender_id', 'receiver_id', 'status'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
