<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaLibraryItem extends Model
{
    protected $fillable = [
        'original_filename',
        'display_name',
        'path',
        'mime_type',
        'file_size',
        'tags',
        'uploaded_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'file_size' => 'integer',
    ];

    protected $appends = ['url', 'formatted_size'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return '/api/storage/' . $this->path;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
