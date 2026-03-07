<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $fillable = [
        'name', 'description', 'image_path',
        'dice', 'dice_duel',
        'wild_value', 'wild_value_duel',
        'wild_ability', 'wild_ability_duel',
        'wild_ability_description', 'wild_ability_description_duel',
        'addon_id', 'available_cooperative', 'available_duel', 'is_available',
        'starting_bonus',
    ];

    protected $casts = [
        'dice' => 'array',
        'dice_duel' => 'array',
        'wild_value' => 'integer',
        'wild_value_duel' => 'integer',
        'available_cooperative' => 'boolean',
        'available_duel' => 'boolean',
        'is_available' => 'boolean',
        'starting_bonus' => 'array',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return '/api/storage/' . $this->image_path;
    }

    public function getDuelDice(): array
    {
        return $this->dice_duel ?? $this->dice;
    }

    public function getDuelWildValue(): int
    {
        return $this->wild_value_duel ?? $this->wild_value;
    }

    public function getDuelWildAbility(): ?string
    {
        return $this->wild_ability_duel ?? $this->wild_ability;
    }

    public function getDuelWildAbilityDescription(): ?string
    {
        return $this->wild_ability_description_duel ?? $this->wild_ability_description;
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }
}
