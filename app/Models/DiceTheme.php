<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiceTheme extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'preview_image', 'data', 'is_active', 'is_default_unlocked'];

    protected $casts = [
        'data' => 'array',
        'is_active' => 'boolean',
        'is_default_unlocked' => 'boolean',
    ];
}
