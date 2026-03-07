<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curse extends Model
{
    protected $fillable = [
        'name', 'description', 'negative_effect', 'positive_effect',
        'negative_effect_duel', 'positive_effect_duel', 'is_available', 'image_path',
    ];

    protected $casts = [
        'negative_effect' => 'array',
        'positive_effect' => 'array',
        'negative_effect_duel' => 'array',
        'positive_effect_duel' => 'array',
        'is_available' => 'boolean',
    ];

    public function getDuelNegativeEffect(): array
    {
        return $this->negative_effect_duel ?? $this->negative_effect;
    }

    public function getDuelPositiveEffect(): array
    {
        return $this->positive_effect_duel ?? $this->positive_effect;
    }
}
