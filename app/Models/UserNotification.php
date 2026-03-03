<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class UserNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'message', 'data', 'read_at', 'claimed_at', 'deleted_at'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'claimed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeUnclaimed(Builder $query): Builder
    {
        return $query->whereNull('claimed_at');
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }
}
