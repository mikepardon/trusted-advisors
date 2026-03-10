<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterLevelOption extends Model
{
    protected $fillable = [
        'name', 'type', 'config', 'available_at_level', 'character_id',
        'is_active', 'max_selections', 'sort_order', 'description', 'icon',
    ];

    protected $casts = [
        'config' => 'array',
        'available_at_level' => 'integer',
        'is_active' => 'boolean',
        'max_selections' => 'integer',
        'sort_order' => 'integer',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
