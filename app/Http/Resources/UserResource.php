<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cosmetic;
use App\Models\User;
use App\Models\UserPassProgress;
use App\Services\SeasonPassService;
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
     *     daily_reward_streak: int,
     *     last_login_at: string|null,
     *     username_chosen: bool,
     *     notification_preferences: array<string, mixed>|null,
     *     referred_by: int|null,
     *     active_dice_theme_slug: string|null,
     *     active_kingdom_style_slug: string|null,
     *     active_title: string|null,
     *     avatar_frame: string|null,
     *     league_tier: int,
     *     league_points: int,
     *     season_pass_tier: int,
     *     season_pass_points: int,
     *     crest: array<string, string>|null,
     *     created_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $seasonPass = $this->seasonPassSummary();

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
            'daily_reward_streak' => $this->daily_reward_streak,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'username_chosen' => (bool) $this->username_chosen,
            'notification_preferences' => $this->notification_preferences,
            'referred_by' => $this->referred_by,
            'active_dice_theme_slug' => $this->active_dice_theme_slug,
            'active_kingdom_style_slug' => $this->active_kingdom_style_slug,
            'active_title' => $this->resolveCosmeticValue($this->active_title_slug, 'title'),
            'avatar_frame' => $this->resolveCosmeticValue($this->active_frame_slug, 'frame'),
            'victory_fx' => $this->resolveCosmeticValue($this->active_victory_fx_slug, 'victory_fx'),
            'league_tier' => $this->league_tier ?? 0,
            'league_points' => $this->currentLeaguePoints(),
            'season_pass_tier' => $seasonPass['tier'],
            'season_pass_points' => $seasonPass['points'],
            'crest' => $this->crest_config,
            'crest_style' => $this->resolveCrestPart(data_get($this->crest_config, 'crest_style'), 'crest_style', 1),
            'crest_colour' => $this->resolveCrestPart(data_get($this->crest_config, 'crest_colour'), 'crest_colour', 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve an equipped crest part slug to its numeric render value (style 1-15
     * or colour 0-4), falling back to the default when nothing is equipped.
     */
    private function resolveCrestPart(mixed $slug, string $type, int $default): int
    {
        if (! is_string($slug)) {
            return $default;
        }

        $value = Cosmetic::query()->where('slug', $slug)->where('type', $type)->value('value');

        return $value === null ? $default : (int) $value;
    }

    /**
     * Read-only pass progress for the active season (points + reached tier).
     *
     * @return array{points: int, tier: int}
     */
    private function seasonPassSummary(): array
    {
        $passService = app(SeasonPassService::class);
        $season = $passService->activeSeason();

        if ($season === null) {
            return ['points' => 0, 'tier' => 0];
        }

        $points = (int) UserPassProgress::query()
            ->where('user_id', $this->id)
            ->where('season_id', $season->id)
            ->value('points');

        return ['points' => $points, 'tier' => $passService->currentTier($points, $season)];
    }

    /**
     * Resolve an equipped cosmetic slug to its display value (title text / frame key).
     */
    private function resolveCosmeticValue(?string $slug, string $type): ?string
    {
        if ($slug === null) {
            return null;
        }

        return Cosmetic::query()
            ->where('slug', $slug)
            ->where('type', $type)
            ->value('value');
    }
}
