<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cosmetic extends Model
{
    /** Maps a cosmetic type to the user column that stores the equipped slug. */
    public const SLOT_COLUMNS = [
        'title' => 'active_title_slug',
        'frame' => 'active_frame_slug',
        'card_back' => 'active_card_back_slug',
        'victory_fx' => 'active_victory_fx_slug',
    ];

    /** Crest parts are assembled into the users.crest_config JSON, keyed by type. */
    public const CREST_TYPES = ['crest_style', 'crest_colour'];

    /** Every cosmetic type an admin may create or edit (single-slot + crest parts). */
    public const TYPES = [
        'title', 'frame', 'card_back', 'victory_fx', 'crest_style', 'crest_colour',
    ];

    /**
     * Resolve equipped crest style/colour slugs to their numeric render values
     * (style 1-5, colour 0-4). Returns a slug => (int) value map for the given
     * crest type, cached so callers can map many users without N+1 queries.
     *
     * @return array<string, int>
     */
    public static function crestValueMap(string $type): array
    {
        return self::query()
            ->where('type', $type)
            ->pluck('value', 'slug')
            ->map(fn (?string $value): int => (int) $value)
            ->all();
    }

    protected $fillable = [
        'type', 'slug', 'name', 'rarity', 'value', 'meta', 'is_default', 'is_available', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_default' => 'boolean',
            'is_available' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cosmetics')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
