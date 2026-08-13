<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit projection of a User for client responses. Every exposed attribute
 * is named here; sensitive/internal columns (auth_id, onesignal tokens, token,
 * ip_address, user_agent, refresh_token, …) are deliberately omitted.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string|null,
     *     email: string|null,
     *     avatar_url: string|null,
     *     level: int,
     *     xp: int,
     *     coins: int,
     *     elo_rating: int,
     *     is_admin: bool,
     *     admin_role: string|null,
     *     is_premium: bool,
     *     premium_expires_at: string|null,
     *     banned_at: string|null,
     *     timeout_count: int,
     *     login_streak: int,
     *     max_login_streak: int,
     *     last_login_at: string|null,
     *     username_chosen: bool,
     *     notification_preferences: array<string, mixed>|null,
     *     referred_by: int|null,
     *     active_dice_theme_slug: string|null,
     *     active_kingdom_style_slug: string|null,
     *     active_title: string|null,
     *     created_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'level' => $this->level,
            'xp' => $this->xp,
            'coins' => $this->coins,
            'elo_rating' => $this->elo_rating,
            'is_admin' => $this->is_admin,
            'admin_role' => $this->admin_role,
            'is_premium' => $this->is_premium,
            'premium_expires_at' => $this->premium_expires_at?->toIso8601String(),
            'banned_at' => $this->banned_at?->toIso8601String(),
            'timeout_count' => $this->timeout_count,
            'login_streak' => $this->login_streak,
            'max_login_streak' => $this->max_login_streak,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'username_chosen' => (bool) $this->username_chosen,
            'notification_preferences' => $this->notification_preferences,
            'referred_by' => $this->referred_by,
            'active_dice_theme_slug' => $this->active_dice_theme_slug,
            'active_kingdom_style_slug' => $this->active_kingdom_style_slug,
            'active_title' => $this->active_title,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
