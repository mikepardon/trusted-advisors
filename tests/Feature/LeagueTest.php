<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LeagueCohort;
use App\Models\LeagueMember;
use App\Models\User;
use App\Services\LeagueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_seats_the_user_and_fills_the_cohort_with_bots(): void
    {
        $user = User::factory()->create(['league_tier' => 0]);

        $member = app(LeagueService::class)->ensureMembership($user);

        $this->assertSame($user->id, $member->user_id);
        $bots = LeagueMember::where('cohort_id', $member->cohort_id)->whereNull('user_id')->count();
        $this->assertGreaterThan(0, $bots);
    }

    public function test_membership_is_reused_within_the_same_week(): void
    {
        $user = User::factory()->create();
        $service = app(LeagueService::class);

        $first = $service->ensureMembership($user);
        $second = $service->ensureMembership($user);

        $this->assertSame($first->id, $second->id);
    }

    public function test_adding_score_accumulates_on_the_member(): void
    {
        $user = User::factory()->create();
        $service = app(LeagueService::class);

        $service->addScore($user, 150);
        $service->addScore($user, 100);

        $member = LeagueMember::where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertSame(250, $member->score);
    }

    public function test_index_returns_standings_for_the_users_tier_and_all_tiers(): void
    {
        $user = User::factory()->create(['league_tier' => 0]);

        $response = $this->actingAs($user, 'web')->getJson('/api/league')->assertOk();

        $response->assertJsonPath('standings.tier_name', 'Wooden');
        $response->assertJsonPath('standings.your_rank', fn ($rank): bool => is_int($rank) && $rank >= 1);
        $response->assertJsonCount(10, 'tiers');
    }

    public function test_processing_a_week_promotes_the_top_and_demotes_the_bottom(): void
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

        $this->artisan('app:process-league-week', ['--week' => '2026-08-03'])->assertSuccessful();

        // Rank 1 (highest score) promotes 2 -> 3 and takes first placement coins.
        $this->assertSame(3, $players[0]->fresh()->league_tier);
        $this->assertSame(500, $players[0]->fresh()->coins);

        // Rank 12 (lowest score) is in the bottom five and demotes 2 -> 1.
        $this->assertSame(1, $players[11]->fresh()->league_tier);
    }

    public function test_processing_the_same_week_twice_does_not_double_reward(): void
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

        $this->artisan('app:process-league-week', ['--week' => '2026-08-03'])->assertSuccessful();
        $this->artisan('app:process-league-week', ['--week' => '2026-08-03'])->assertSuccessful();

        // The second run finds the cohort already processed and settles nothing again:
        // the top player keeps exactly one promotion and one placement payout.
        $this->assertSame(3, $players[0]->fresh()->league_tier);
        $this->assertSame(500, $players[0]->fresh()->coins);
        $this->assertNotNull($cohort->fresh()->processed_at);
    }
}
