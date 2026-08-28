<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonPassTier extends Model
{
    protected $fillable = [
        'season_id', 'tier', 'points_required', 'reward_coins', 'reward_cosmetic_id', 'reward_item_id', 'name',
    ];

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'points_required' => 'integer',
            'reward_coins' => 'integer',
            'reward_cosmetic_id' => 'integer',
            'reward_item_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** @return BelongsTo<Cosmetic, $this> */
    public function rewardCosmetic(): BelongsTo
    {
        return $this->belongsTo(Cosmetic::class, 'reward_cosmetic_id');
    }

    /** @return BelongsTo<Item, $this> */
    public function rewardItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'reward_item_id');
    }
}
