<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    protected $fillable = ['key', 'name', 'description', 'icon', 'category', 'criteria', 'reward_type', 'reward_id'];

    protected $casts = [
        'criteria' => 'array',
    ];

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
