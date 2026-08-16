<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserCharacter;
use App\Models\UserPassProgress;
use App\Services\GameCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGames;
use Tests\TestCase;

class GameCompletionRewardsTest extends TestCase
{
    use InteractsWithGames;
    use RefreshDatabase;

    private function complete(User $user, array $options): void
    {
        app(GameCompletionService::class)->processCompletion($this->completedGameFor($user, $options));
    }

    public function test_online_win_awards_account_xp_character_xp_season_points_and_league_score(): void
    {
        $season = $this->makeActiveSeason();
        $user = User::factory()->create(['xp' => 0, 'coins' => 0]);

        $this->complete($user, ['mode' => 'online', 'type' => 'cooperative', 'win' => true]);

        // base 50 + coop win 50 = 100, x1.5 online = 150, to both account and the played advisor.
        $this->assertSame(150, $user->fresh()->xp);
        $this->assertSame(150, UserCharacter::query()->where('user_id', $user->id)->value('xp'));
        $this->assertGreaterThan(0, $user->fresh()->coins);

        // Season Pass points (win = 150) and — because it's online — a league score of 150.
        $this->assertSame(150, UserPassProgress::query()
            ->where('user_id', $user->id)->where('season_id', $season->id)->value('points'));
        $this->assertSame(150, LeagueMember::query()->where('user_id', $user->id)->value('score'));
    }

    public function test_offline_single_game_awards_season_points_but_no_league_score(): void
    {
        $season = $this->makeActiveSeason();
        $user = User::factory()->create(['xp' => 0]);

        $this->complete($user, ['mode' => 'single', 'type' => 'cooperative', 'win' => false]);

        // Offline loss: base 50 only, no win bonus, no online multiplier.
        $this->assertSame(50, $user->fresh()->xp);
        // Season Pass still accrues (loss = 100 points) for any non-custom game...
        $this->assertSame(100, UserPassProgress::query()
            ->where('user_id', $user->id)->where('season_id', $season->id)->value('points'));
        // ...but the competitive league does not, so the player is never even seated.
        $this->assertFalse(LeagueMember::query()->where('user_id', $user->id)->exists());
    }

    public function test_pass_and_play_awards_season_points_but_no_league_score(): void
    {
        $season = $this->makeActiveSeason();
        $user = User::factory()->create(['xp' => 0]);

        $this->complete($user, ['mode' => 'pass_and_play', 'type' => 'cooperative', 'win' => true]);

        $this->assertSame(150, UserPassProgress::query()
            ->where('user_id', $user->id)->where('season_id', $season->id)->value('points'));
        $this->assertFalse(LeagueMember::query()->where('user_id', $user->id)->exists());
    }

    public function test_completion_is_idempotent_and_only_awards_once(): void
    {
        $this->makeActiveSeason();
        $user = User::factory()->create(['xp' => 0]);
        $game = $this->completedGameFor($user, ['mode' => 'online', 'type' => 'cooperative', 'win' => true]);

        $service = app(GameCompletionService::class);
        $service->processCompletion($game);
        // Simulate the completion path firing again (retry / both players / bot+human).
        $service->processCompletion($game->fresh());
        $service->processCompletion($game->fresh());

        // XP was granted exactly once despite three completion calls.
        $this->assertSame(150, $user->fresh()->xp);
        $this->assertSame(150, UserPassProgress::query()->where('user_id', $user->id)->value('points'));
        $this->assertSame(150, LeagueMember::query()->where('user_id', $user->id)->value('score'));
    }

    public function test_custom_games_award_nothing(): void
    {
        $this->makeActiveSeason();
        $user = User::factory()->create(['xp' => 0, 'coins' => 0]);

        $this->complete($user, ['mode' => 'online', 'type' => 'cooperative', 'win' => true, 'is_custom' => true]);

        $this->assertSame(0, $user->fresh()->xp);
        $this->assertSame(0, $user->fresh()->coins);
        $this->assertFalse(UserPassProgress::query()->where('user_id', $user->id)->exists());
        $this->assertFalse(LeagueMember::query()->where('user_id', $user->id)->exists());
    }
}
