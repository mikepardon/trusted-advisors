<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SoundAsset extends Model
{
    protected $fillable = ['key', 'label', 'category', 'path'];

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        try {
            return Storage::disk('s3')->url($this->path);
        } catch (\Exception $e) {
            return null;
        }
    }
}
