<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'description', 'effect', 'effect_type', 'is_negative', 'is_consumable', 'addon_id'];

    protected $casts = [
        'effect' => 'array',
        'is_negative' => 'boolean',
        'is_consumable' => 'boolean',
    ];
}
