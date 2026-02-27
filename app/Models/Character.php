<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Character extends Model
{
    protected $fillable = ['name', 'description', 'image_path', 'dice', 'wild_value', 'wild_ability', 'wild_ability_description'];

    protected $casts = [
        'dice' => 'array',
        'wild_value' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        try {
            return Storage::disk('s3')->url($this->image_path);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }
}
