<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUnlockable extends Model
{
    protected $fillable = ['user_id', 'unlockable_id', 'unlocked_at'];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unlockable(): BelongsTo
    {
        return $this->belongsTo(Unlockable::class);
    }
}
