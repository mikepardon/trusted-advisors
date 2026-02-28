<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unlockable extends Model
{
    protected $fillable = ['type', 'entity_id', 'unlock_method', 'unlock_value'];

    public function userUnlockables(): HasMany
    {
        return $this->hasMany(UserUnlockable::class);
    }

    public function entity()
    {
        if ($this->type === 'character') {
            return $this->belongsTo(Character::class, 'entity_id');
        }
        return $this->belongsTo(Item::class, 'entity_id');
    }
}
