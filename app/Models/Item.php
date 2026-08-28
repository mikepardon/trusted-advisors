<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    public const CADENCE_PASSIVE = 'passive';
    public const CADENCE_PER_ROUND = 'per_round';
    public const CADENCE_PER_GAME = 'per_game';

    protected $fillable = ['name', 'description', 'effect', 'effect_duel', 'effect_type', 'is_negative', 'is_consumable', 'addon_id', 'available_cooperative', 'available_duel', 'target', 'is_starter', 'cadence', 'type', 'icon_key', 'rarity'];

    protected $casts = [
        'effect' => 'array',
        'effect_duel' => 'array',
        'is_negative' => 'boolean',
        'is_consumable' => 'boolean',
        'available_cooperative' => 'boolean',
        'available_duel' => 'boolean',
        'is_starter' => 'boolean',
    ];

    public function getDuelEffect(): ?array
    {
        return $this->effect_duel ?? $this->effect;
    }

    public function isPassive(): bool
    {
        return $this->cadence === self::CADENCE_PASSIVE;
    }

    public function isPerRound(): bool
    {
        return $this->cadence === self::CADENCE_PER_ROUND;
    }

    public function isPerGame(): bool
    {
        return $this->cadence === self::CADENCE_PER_GAME;
    }
}
