<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LeagueCohort;
use App\Models\LeagueMember;
use App\Models\LeagueResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueResultTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: LeagueCohort, 1: list<User>}
     */
    private function seedProcessedCohort(): array
    {
        $cohort = LeagueCohort::create(['tier' => 2, 'week_start' => '2026-08-03']);

        $players = [];
        for ($i = 0; $i < 12; $i++) {
            $player = User::factory()->create(['league_tier' => 2, 'coins' => 0]);
            LeagueMember::create([
                'cohort_id' => $cohort->id,
                'user_id' => $player->id,
                'score' => 120 - ($i * 10),
            ]);
            $players[] = $player;
        }

        return [$cohort, $players];
    }

    public function test_processing_a_week_snapshots_the_winners_result(): void
    {
        [, $players] = $this->seedProcessedCohort();

        $this->artisan('app:process-league-week', ['--week' => '2026-08-03'])->assertSuccessful();

        $winner = LeagueResult::query()->where('user_id', $players[0]->id)->first();

        $this->assertNotNull($winner);
        $this->assertSame(1, $winner->rank);
        $this->assertSame(12, $winner->total);
        $this->assertSame(2, $winner->tier_before);
        $this->assertSame(3, $winner->tier_after);
        $this->assertSame(500, $winner->coins_earned);
    }

    public function test_reprocessing_a_week_does_not_duplicate_snapshots(): void
    {
        $this->seedProcessedCohort();

        $this->artisan('app:process-league-week', ['--week' => '2026-08-03'])->assertSuccessful();
        $this->artisan('app:process-league-week', ['--week' => '2026-08-03'])->assertSuccessful();

        $this->assertSame(12, LeagueResult::query()->count());
    }

    public function test_last_result_endpoint_returns_then_clears_the_unseen_result(): void
    {
        $cohort = LeagueCohort::create(['tier' => 1, 'week_start' => '2026-08-03']);
        $user = User::factory()->create(['league_tier' => 2]);
        LeagueResult::create([
            'user_id' => $user->id,
            'cohort_id' => $cohort->id,
            'week_start' => '2026-08-03',
            'tier' => 1,
            'rank' => 1,
            'total' => 10,
            'tier_before' => 1,
            'tier_after' => 2,
            'coins_earned' => 500,
        ]);

        $this->actingAs($user, 'web')->getJson('/api/league/last-result')
            ->assertOk()
            ->assertJsonPath('result.rank', 1)
            ->assertJsonPath('result.promoted', true)
            ->assertJsonPath('result.tier_before.name', 'Bronze')
            ->assertJsonPath('result.tier_after.name', 'Iron');

        $this->actingAs($user, 'web')->postJson('/api/league/last-result/seen')->assertOk();

        $this->actingAs($user, 'web')->getJson('/api/league/last-result')
            ->assertOk()
            ->assertJsonPath('result', null);
    }
}
