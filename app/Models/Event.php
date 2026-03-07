<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title', 'effect', 'stat_modifiers', 'mechanic', 'mechanic_data',
        'stat_modifiers_duel', 'mechanic_duel', 'mechanic_data_duel',
        'addon_id', 'available_cooperative', 'available_duel',
    ];

    protected $casts = [
        'stat_modifiers' => 'array',
        'mechanic_data' => 'array',
        'stat_modifiers_duel' => 'array',
        'mechanic_data_duel' => 'array',
        'available_cooperative' => 'boolean',
        'available_duel' => 'boolean',
    ];

    public function getDuelStatModifiers(): ?array
    {
        return $this->stat_modifiers_duel ?? $this->stat_modifiers;
    }

    public function getDuelMechanic(): ?string
    {
        return $this->mechanic_duel ?? $this->mechanic;
    }

    public function getDuelMechanicData(): ?array
    {
        return $this->mechanic_data_duel ?? $this->mechanic_data;
    }
}
