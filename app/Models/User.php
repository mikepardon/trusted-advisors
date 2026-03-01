<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'name',
        'email',
        'password',
        'is_admin',
        'onesignal_player_id',
        'onesignal_email_token',
        'xp',
        'level',
        'elo_rating',
        'coins',
        'login_streak',
        'max_login_streak',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'xp' => 'integer',
            'level' => 'integer',
            'elo_rating' => 'integer',
            'coins' => 'integer',
            'login_streak' => 'integer',
            'max_login_streak' => 'integer',
            'last_login_at' => 'datetime',
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
        // Total XP needed to reach this level: 100 * N * (N + 1) / 2
        return (int) (100 * $level * ($level + 1) / 2);
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
}
