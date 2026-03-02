<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $fillable = ['name', 'description', 'image_path', 'dice', 'wild_value', 'wild_ability', 'wild_ability_description', 'addon_id', 'available_cooperative', 'available_duel', 'is_available'];

    protected $casts = [
        'dice' => 'array',
        'wild_value' => 'integer',
        'available_cooperative' => 'boolean',
        'available_duel' => 'boolean',
        'is_available' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return '/api/storage/' . $this->image_path;
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }
}
