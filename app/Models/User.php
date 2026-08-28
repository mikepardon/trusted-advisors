<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values applied to every new user.
     *
     * Everyone starts equipped with the free "The Newbie" title (seeded as the
     * default title cosmetic), so their name always carries a vanity title.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active_title_slug' => 'the-newbie',
        'active_frame_slug' => 'iron-ring',
        'active_card_back_slug' => 'parchment',
        'active_victory_fx_slug' => 'gold-rain',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'auth_id',
        'name',
        'email',
        'password',
        'avatar_url',
        'is_admin',
        'admin_role',
        'is_bot',
        'onesignal_player_id',
        'onesignal_email_token',
        'xp',
        'level',
        'elo_rating',
        'coins',
        'login_streak',
        'max_login_streak',
        'daily_reward_claimed_at',
        'daily_reward_streak',
        'free_chest_claimed_at',
        'active_title_slug',
        'active_frame_slug',
        'active_card_back_slug',
        'active_victory_fx_slug',
        'crest_config',
        'league_tier',
        'timeout_count',
        'last_login_at',
        'refresh_token',
        'username_chosen',
        'notification_preferences',
        'referral_code',
        'referred_by',
        'active_dice_theme_slug',
        'active_kingdom_style_slug',
        'active_title',
        'is_premium',
        'premium_expires_at',
        'app_review_prompted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'refresh_token',
        'is_bot',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_bot' => 'boolean',
            'xp' => 'integer',
            'level' => 'integer',
            'elo_rating' => 'integer',
            'coins' => 'integer',
            'login_streak' => 'integer',
            'max_login_streak' => 'integer',
            'daily_reward_claimed_at' => 'datetime',
            'free_chest_claimed_at' => 'immutable_datetime',
            'crest_config' => 'array',
            'league_tier' => 'integer',
            'daily_reward_streak' => 'integer',
            'timeout_count' => 'integer',
            'last_login_at' => 'datetime',
            'banned_at' => 'datetime',
            'username_chosen' => 'boolean',
            'notification_preferences' => 'array',
            'is_premium' => 'boolean',
            'premium_expires_at' => 'datetime',
            'app_review_prompted_at' => 'datetime',
        ];
    }

    public static function calculateLevel(int $xp): int
    {
        $level = 1;
        while ($xp >= self::xpForLevel($level + 1)) {
            $level++;
        }

        return $level;
    }

    public static function xpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        // Total cumulative XP to reach this level, on a cubic (sum-of-squares) curve so
        // higher levels ramp up rather than staying flat. 50 * sum_{k=1}^{L-1} k^2.
        // L2 = 50, L3 = 250, L4 = 700, L5 = 1500, L6 = 2750, L7 = 4550, L8 = 7000...
        return (int) (50 * ($level - 1) * $level * (2 * $level - 1) / 6);
    }

    public function activeDiceTheme(): BelongsTo
    {
        return $this->belongsTo(DiceTheme::class, 'active_dice_theme_slug', 'slug');
    }

    public function activeKingdomStyle(): BelongsTo
    {
        return $this->belongsTo(KingdomStyle::class, 'active_kingdom_style_slug', 'slug');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function generateReferralCode(): string
    {
        if ($this->referral_code) {
            return $this->referral_code;
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        $this->referral_code = $code;
        $this->save();

        return $code;
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'receiver_id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function unlockables(): HasMany
    {
        return $this->hasMany(UserUnlockable::class);
    }

    /** @return BelongsToMany<Cosmetic, $this> */
    public function cosmetics(): BelongsToMany
    {
        return $this->belongsToMany(Cosmetic::class, 'user_cosmetics')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function ownsCosmetic(Cosmetic $cosmetic): bool
    {
        return $cosmetic->is_default
            || $this->cosmetics()->whereKey($cosmetic->getKey())->exists();
    }

    public function grantCosmetic(Cosmetic $cosmetic): void
    {
        $this->cosmetics()->syncWithoutDetaching([
            $cosmetic->getKey() => ['unlocked_at' => now()],
        ]);
    }

    /**
     * The user's league score for the current week (read-only; does not seat them
     * in a cohort). Zero until they have played and been added to one.
     */
    public function currentLeaguePoints(): int
    {
        $week = now()->startOfWeek(CarbonInterface::MONDAY)->toDateString();

        return (int) LeagueMember::query()
            ->where('user_id', $this->id)
            ->whereHas('cohort', fn ($query) => $query->whereDate('week_start', $week))
            ->value('score');
    }

    public function userCharacters(): HasMany
    {
        return $this->hasMany(UserCharacter::class);
    }

    public function ownsCharacter(int $characterId): bool
    {
        return $this->userCharacters()->where('character_id', $characterId)->exists();
    }

    public function items(): HasMany
    {
        return $this->hasMany(UserItem::class);
    }

    /**
     * IDs of every item this user may bring into a game: the shared starter set
     * plus any items unlocked through the Unlockable/UserUnlockable system.
     *
     * @return list<int>
     */
    public function ownedItemIds(): array
    {
        $starterIds = Item::query()->where('is_starter', true)->pluck('id');

        $unlockedIds = Unlockable::query()
            ->where('type', 'item')
            ->whereIn('id', $this->unlockables()->select('unlockable_id'))
            ->pluck('entity_id');

        return $starterIds->concat($unlockedIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function ownsItem(int $itemId): bool
    {
        return in_array($itemId, $this->ownedItemIds(), true);
    }

    /**
     * IDs of the (max 3) items in the user's ready loadout, in slot order.
     *
     * @return list<int>
     */
    public function equippedItemIds(): array
    {
        return $this->items()
            ->where('equipped', true)
            ->orderBy('slot')
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function eloHistory(): HasMany
    {
        return $this->hasMany(UserEloHistory::class);
    }

    public function coinTransactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function paymentCustomers(): HasMany
    {
        return $this->hasMany(PaymentCustomer::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription(): ?UserSubscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    public function isPremium(): bool
    {
        if (! $this->is_premium) {
            return false;
        }

        if ($this->premium_expires_at && $this->premium_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function needsAdvisors(): bool
    {
        if ($this->is_bot) {
            return false;
        }

        return $this->userCharacters()->count() === 0;
    }

    public function hasAdminRole(string ...$roles): bool
    {
        if ($this->admin_role === 'super_admin') {
            return true;
        }

        return in_array($this->admin_role, $roles);
    }

    public function isSuperAdmin(): bool
    {
        return $this->admin_role === 'super_admin';
    }

    public function wantsPushNotification(string $category): bool
    {
        $prefs = $this->notification_preferences;
        if ($prefs === null) {
            return true;
        }

        return $prefs['push_'.$category] ?? true;
    }

    public function recordCoinTransaction(int $amount, string $type, string $source, ?int $referenceId = null, string $description = ''): void
    {
        if ($amount === 0) {
            return;
        }

        CoinTransaction::create([
            'user_id' => $this->id,
            'amount' => $amount,
            'type' => $type,
            'source' => $source,
            'reference_id' => $referenceId,
            'description' => $description,
            'balance_after' => $this->coins,
        ]);
    }
}
