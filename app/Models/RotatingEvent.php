<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RotatingEvent extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image_url',
        'game_type',
        'game_mode',
        'modifiers',
        'card_pool',
        'item_pool',
        'event_pool',
        'character_pool',
        'curse_pool',
        'fixed_event_id',
        'total_rounds',
        'xp_config',
        'affects_elo',
        'theme_color',
        'reward_coins',
        'max_attempts',
        'starts_at',
        'ends_at',
        'is_active',
        'visibility',
        'created_by',
    ];

    protected $casts = [
        'modifiers' => 'array',
        'card_pool' => 'array',
        'item_pool' => 'array',
        'event_pool' => 'array',
        'character_pool' => 'array',
        'curse_pool' => 'array',
        'xp_config' => 'array',
        'affects_elo' => 'boolean',
        'reward_coins' => 'integer',
        'max_attempts' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RotatingEventEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fixedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'fixed_event_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
