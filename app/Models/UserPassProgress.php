<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPassProgress extends Model
{
    protected $table = 'user_pass_progress';

    protected $fillable = ['user_id', 'season_id', 'points', 'claimed_tiers'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'season_id' => 'integer',
            'points' => 'integer',
            'claimed_tiers' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
