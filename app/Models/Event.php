<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = ['title', 'effect', 'stat_modifiers', 'mechanic', 'mechanic_data', 'addon_id', 'available_cooperative', 'available_duel'];

    protected $casts = [
        'stat_modifiers' => 'array',
        'mechanic_data' => 'array',
        'available_cooperative' => 'boolean',
        'available_duel' => 'boolean',
    ];
}
