<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoundAsset extends Model
{
    protected $fillable = ['key', 'label', 'category', 'path'];

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return '/api/storage/' . $this->path;
    }
}
