<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminGift extends Model
{
    protected $fillable = ['title', 'note', 'reward_xp', 'reward_coins', 'reward_character_id', 'created_by', 'recipient_count'];

    protected $casts = [
        'reward_xp' => 'integer',
        'reward_coins' => 'integer',
        'recipient_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rewardCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'reward_character_id');
    }
}
