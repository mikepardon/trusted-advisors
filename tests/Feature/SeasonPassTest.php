<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cosmetic;
use App\Models\Season;
use App\Models\SeasonPassTier;
use App\Models\User;
use App\Models\UserPassProgress;
use App\Services\SeasonPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonPassTest extends TestCase
{
    use RefreshDatabase;

    private function activeSeason(): Season
    {
        return Season::create([
            'name' => 'Season 1',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    private function coinTier(Season $season, int $tier, int $points, int $coins): SeasonPassTier
    {
        return SeasonPassTier::create([
            'season_id' => $season->id,
            'tier' => $tier,
            'points_required' => $points,
            'reward_coins' => $coins,
            'name' => "{$coins} Gold",
        ]);
    }

    public function test_index_returns_null_when_no_season_is_active(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->getJson('/api/season-pass')
            ->assertOk()
            ->assertJson(['season' => null]);
    }

    public function test_index_reports_points_and_tier_flags(): void
    {
        $season = $this->activeSeason();
        $this->coinTier($season, 1, 100, 50);
        $this->coinTier($season, 2, 300, 80);
        $user = User::factory()->create();
        UserPassProgress::create(['user_id' => $user->id, 'season_id' => $season->id, 'points' => 150, 'claimed_tiers' => []]);

        $response = $this->actingAs($user, 'web')->getJson('/api/season-pass')->assertOk();

        $response->assertJsonPath('points', 150);
        $response->assertJsonPath('current_tier', 1);
        $response->assertJsonPath('tiers.0.claimable', true);
        $response->assertJsonPath('tiers.1.reached', false);
    }

    public function test_service_accumulates_points_for_the_active_season(): void
    {
        $season = $this->activeSeason();
        $user = User::factory()->create();

        app(SeasonPassService::class)->addPoints($user, 150);
        app(SeasonPassService::class)->addPoints($user, 100);

        $progress = UserPassProgress::where('user_id', $user->id)->where('season_id', $season->id)->first();
        $this->assertNotNull($progress);
        $this->assertSame(250, $progress->points);
    }

    public function test_claiming_a_reached_tier_grants_coins_once(): void
    {
        $season = $this->activeSeason();
        $this->coinTier($season, 1, 100, 50);
        $user = User::factory()->create(['coins' => 10]);
        UserPassProgress::create(['user_id' => $user->id, 'season_id' => $season->id, 'points' => 120, 'claimed_tiers' => []]);

        $this->actingAs($user, 'web')->postJson('/api/season-pass/claim', ['tier' => 1])
            ->assertOk()
            ->assertJsonPath('granted.coins', 50);

        $this->assertSame(60, $user->fresh()->coins);

        // A second claim of the same tier is rejected.
        $this->actingAs($user, 'web')->postJson('/api/season-pass/claim', ['tier' => 1])
            ->assertStatus(422);
        $this->assertSame(60, $user->fresh()->coins);
    }

    public function test_claiming_a_tier_grants_the_cosmetic(): void
    {
        $season = $this->activeSeason();
        $cosmetic = Cosmetic::create([
            'type' => 'title', 'slug' => 'the-wise', 'name' => 'The Wise',
            'rarity' => 'rare', 'value' => 'The Wise', 'is_available' => true, 'sort' => 0,
        ]);
        SeasonPassTier::create([
            'season_id' => $season->id, 'tier' => 1, 'points_required' => 100,
            'reward_coins' => 0, 'reward_cosmetic_id' => $cosmetic->id, 'name' => 'The Wise',
        ]);
        $user = User::factory()->create();
        UserPassProgress::create(['user_id' => $user->id, 'season_id' => $season->id, 'points' => 100, 'claimed_tiers' => []]);

        $this->actingAs($user, 'web')->postJson('/api/season-pass/claim', ['tier' => 1])
            ->assertOk()
            ->assertJsonPath('granted.cosmetic', 'The Wise');

        $this->assertTrue($user->fresh()->ownsCosmetic($cosmetic));
    }

    public function test_cannot_claim_a_tier_that_is_not_yet_reached(): void
    {
        $season = $this->activeSeason();
        $this->coinTier($season, 1, 500, 50);
        $user = User::factory()->create();
        UserPassProgress::create(['user_id' => $user->id, 'season_id' => $season->id, 'points' => 100, 'claimed_tiers' => []]);

        $this->actingAs($user, 'web')->postJson('/api/season-pass/claim', ['tier' => 1])
            ->assertStatus(422);
    }
}
