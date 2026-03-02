<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'description', 'effect', 'effect_type', 'is_negative', 'is_consumable', 'addon_id', 'available_cooperative', 'available_duel'];

    protected $casts = [
        'effect' => 'array',
        'is_negative' => 'boolean',
        'is_consumable' => 'boolean',
        'available_cooperative' => 'boolean',
        'available_duel' => 'boolean',
    ];
}
