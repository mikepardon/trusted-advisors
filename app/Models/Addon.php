<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function dailyChallenges(): HasMany
    {
        return $this->hasMany(DailyChallenge::class);
    }
}
