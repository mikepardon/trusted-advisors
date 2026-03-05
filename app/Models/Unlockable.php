<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unlockable extends Model
{
    protected $fillable = [
        'type', 'entity_id', 'unlock_method', 'unlock_value',
        'cash_price_cents', 'stripe_price_id', 'apple_product_id', 'google_product_id',
    ];

    protected function casts(): array
    {
        return [
            'cash_price_cents' => 'integer',
        ];
    }

    public function hasCashPrice(): bool
    {
        return $this->cash_price_cents !== null && $this->cash_price_cents > 0;
    }

    public function userUnlockables(): HasMany
    {
        return $this->hasMany(UserUnlockable::class);
    }

    public function entity()
    {
        if ($this->type === 'character') {
            return $this->belongsTo(Character::class, 'entity_id');
        }
        if ($this->type === 'dice_theme') {
            return $this->belongsTo(DiceTheme::class, 'entity_id');
        }
        if ($this->type === 'kingdom_style') {
            return $this->belongsTo(KingdomStyle::class, 'entity_id');
        }
        return $this->belongsTo(Item::class, 'entity_id');
    }
}
