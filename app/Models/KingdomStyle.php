<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KingdomStyle extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'css_vars', 'background_image_path', 'is_default', 'is_active', 'is_default_unlocked'];

    protected $casts = [
        'css_vars' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'is_default_unlocked' => 'boolean',
    ];

    protected $appends = ['background_image_url'];

    public function getBackgroundImageUrlAttribute(): ?string
    {
        if (!$this->background_image_path) {
            return null;
        }

        return '/api/storage/' . $this->background_image_path;
    }
}
