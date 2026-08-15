<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayer extends Model
{
    protected $fillable = [
        'game_id', 'user_id', 'character_id', 'player_number', 'lost_dice',
        'is_bot', 'bot_difficulty', 'ability_uses', 'ability_active_this_round', 'item_decided',
        'card_redraw_uses', 'card_redraws_used', 'extra_item_slots', 'dice_overrides', 'passive_bonuses',
    ];

    /**
     * Expose the player's equipped crest shape + colour on the serialised payload
     * so the duel board can render each player's league crest.
     *
     * @var list<string>
     */
    protected $appends = [
        'crest_style',
        'crest_colour',
    ];

    /** @var array<string, int>|null Cached slug => value maps for crest parts. */
    private static ?array $crestStyleMap = null;

    /** @var array<string, int>|null */
    private static ?array $crestColourMap = null;

    protected $casts = [
        'lost_dice' => 'integer',
        'is_bot' => 'boolean',
        'ability_uses' => 'integer',
        'ability_active_this_round' => 'boolean',
        'item_decided' => 'boolean',
        'card_redraw_uses' => 'integer',
        'card_redraws_used' => 'integer',
        'extra_item_slots' => 'integer',
        'dice_overrides' => 'array',
        'passive_bonuses' => 'array',
    ];

    /**
     * The player's equipped crest style (1-15). Real players with no chosen
     * style fall back to 1 (the seeded default, matching their profile); bots
     * (no user) get a deterministic 1-5 style for visual variety.
     */
    protected function crestStyle(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->crestPartValue(
                'crest_style',
                $this->user_id === null ? ((int) $this->id % 5) + 1 : 1,
            ),
        );
    }

    /**
     * The player's equipped crest colour (0-4). Defaults to 0 (Wooden).
     */
    protected function crestColour(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->crestPartValue('crest_colour', 0),
        );
    }

    /**
     * Resolve an equipped crest part slug (from the user's crest_config) to its
     * numeric render value via a cached slug => value map.
     */
    private function crestPartValue(string $type, int $default): int
    {
        $user = $this->relationLoaded('user') ? $this->user : null;
        $slug = data_get($user?->crest_config, $type);

        if (! is_string($slug)) {
            return $default;
        }

        $map = $type === 'crest_style'
            ? self::$crestStyleMap ??= Cosmetic::crestValueMap('crest_style')
            : self::$crestColourMap ??= Cosmetic::crestValueMap('crest_colour');

        return $map[$slug] ?? $default;
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GamePlayerItem::class);
    }

    public function curses(): HasMany
    {
        return $this->hasMany(GamePlayerCurse::class);
    }

    public function kingdom(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GamePlayerKingdom::class);
    }
}
