<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = [
        'title', 'description', 'sort_order',
        'difficulty', 'difficulty_duel',
        'positive_effects', 'positive_effects_duel',
        'negative_effects', 'negative_effects_duel',
        'positive_flavor', 'negative_flavor', 'category',
        'available_cooperative', 'available_duel',
    ];

    protected $casts = [
        'difficulty' => 'integer',
        'difficulty_duel' => 'integer',
        'positive_effects' => 'array',
        'positive_effects_duel' => 'array',
        'negative_effects' => 'array',
        'negative_effects_duel' => 'array',
        'available_cooperative' => 'boolean',
        'available_duel' => 'boolean',
    ];

    public function getDuelDifficulty(): int
    {
        return $this->difficulty_duel ?? $this->difficulty;
    }

    public function getDuelPositiveEffects(): ?array
    {
        return $this->positive_effects_duel ?? $this->positive_effects;
    }

    public function getDuelNegativeEffects(): ?array
    {
        return $this->negative_effects_duel ?? $this->negative_effects;
    }
}
