<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        'is_bot',
        'onesignal_player_id',
        'onesignal_email_token',
        'xp',
        'level',
        'elo_rating',
        'coins',
        'login_streak',
        'max_login_streak',
        'timeout_count',
        'last_login_at',
        'refresh_token',
        'username_chosen',
        'notification_preferences',
        'referral_code',
        'referred_by',
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
            'timeout_count' => 'integer',
            'last_login_at' => 'datetime',
            'banned_at' => 'datetime',
            'username_chosen' => 'boolean',
            'notification_preferences' => 'array',
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
        // Total cumulative XP needed to reach this level
        // Level 1 = 0 (start here), Level 2 = 100, Level 3 = 300, Level 4 = 600...
        return (int) (100 * ($level - 1) * $level / 2);
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

    public function eloHistory(): HasMany
    {
        return $this->hasMany(UserEloHistory::class);
    }

    public function coinTransactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }

    public function wantsPushNotification(string $category): bool
    {
        $prefs = $this->notification_preferences;
        if ($prefs === null) return true;
        return $prefs['push_' . $category] ?? true;
    }

    public function recordCoinTransaction(int $amount, string $type, string $source, ?int $referenceId = null, string $description = ''): void
    {
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
