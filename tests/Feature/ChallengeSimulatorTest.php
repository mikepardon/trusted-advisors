<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SimulateDailyChallengeJob;
use App\Models\Card;
use App\Models\Character;
use App\Models\DailyChallenge;
use App\Models\Event;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Services\ChallengeSimulator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ChallengeSimulatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A strong advisor (three all-six dice → a guaranteed roll of 18) so every attempt beats
     * the easy cards; one event with no modifiers to keep the scenario clean.
     */
    private function strongAdvisor(): Character
    {
        Event::create(['title' => 'Calm', 'effect' => 'x', 'available_cooperative' => true, 'available_duel' => false]);

        return Character::create([
            'name' => 'Champion',
            'description' => 'x',
            'dice' => [[6, 6, 6, 6, 6, 6], [6, 6, 6, 6, 6, 6], [6, 6, 6, 6, 6, 6]],
        ]);
    }

    private function makeChallenge(Character $character, array $goal, int $rounds = 12): DailyChallenge
    {
        return DailyChallenge::create([
            'date' => Carbon::today(),
            'title' => 'Sim Target',
            'description' => 'x',
            'criteria' => [
                'mode' => 'cooperative',
                'rounds' => $rounds,
                'start' => ['all' => 8],
                'goal' => $goal,
                'seed_character_id' => $character->id,
            ],
            'reward_xp' => 100,
            'is_manual' => false,
        ]);
    }

    public function test_a_trivially_winnable_challenge_reports_a_full_success_rate_and_fast_average(): void
    {
        $character = $this->strongAdvisor();

        // Each success adds +3 wealth with no drain, so wealth climbs 8 → 11 → 14 and clears
        // the target of 12 on the second month, every single run.
        for ($i = 1; $i <= 4; $i++) {
            Card::create([
                'title' => "Boon {$i}", 'description' => 'x', 'sort_order' => $i, 'difficulty' => 5,
                'positive_effects' => ['wealth' => 3], 'negative_effects' => [],
                'available_cooperative' => true, 'available_duel' => false,
            ]);
        }

        $challenge = $this->makeChallenge($character, ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 12]);

        $result = app(ChallengeSimulator::class)->simulate($challenge, 20);

        $this->assertSame(20, $result['runs']);
        $this->assertSame(100, $result['success_rate']);
        $this->assertSame(2.0, $result['avg_months']);

        // The aggregate is cached on the row for the admin table.
        $challenge->refresh();
        $this->assertSame(100, $challenge->sim_success_rate);
        $this->assertSame(2.0, $challenge->sim_avg_months);
        $this->assertSame(20, $challenge->sim_runs);
        $this->assertNotNull($challenge->sim_computed_at);
    }

    public function test_an_unwinnable_challenge_reports_a_zero_success_rate_and_no_average(): void
    {
        $character = $this->strongAdvisor();

        // Every round the negative-role card drains 4 happiness (applied even on a success),
        // while wealth crawls +2 toward a distant target of 20. Happiness starts at 8 and
        // collapses on the second month — a loss — long before wealth arrives.
        for ($i = 1; $i <= 4; $i++) {
            Card::create([
                'title' => "Trap {$i}", 'description' => 'x', 'sort_order' => $i, 'difficulty' => 5,
                'positive_effects' => ['wealth' => 2], 'negative_effects' => ['happiness' => -4],
                'available_cooperative' => true, 'available_duel' => false,
            ]);
        }

        $challenge = $this->makeChallenge($character, ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 20]);

        $result = app(ChallengeSimulator::class)->simulate($challenge, 20);

        $this->assertSame(0, $result['success_rate']);
        $this->assertSame(0, $result['wins']);
        $this->assertNull($result['avg_months']);

        $challenge->refresh();
        $this->assertSame(0, $challenge->sim_success_rate);
        $this->assertNull($challenge->sim_avg_months);
    }

    public function test_simulating_leaves_no_throwaway_games_or_users_behind(): void
    {
        $character = $this->strongAdvisor();
        Card::create([
            'title' => 'Boon', 'description' => 'x', 'sort_order' => 1, 'difficulty' => 5,
            'positive_effects' => ['wealth' => 3], 'negative_effects' => [],
            'available_cooperative' => true, 'available_duel' => false,
        ]);
        $challenge = $this->makeChallenge($character, ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 12]);

        app(ChallengeSimulator::class)->simulate($challenge, 10);

        // Every simulated game and the throwaway sim user were rolled back.
        $this->assertSame(0, Game::count());
        $this->assertSame(0, GamePlayer::count());
        $this->assertSame(0, User::where('name', 'Challenge Simulator')->count());
    }

    public function test_the_command_simulates_a_challenge_and_stores_the_result(): void
    {
        $character = $this->strongAdvisor();
        Card::create([
            'title' => 'Boon', 'description' => 'x', 'sort_order' => 1, 'difficulty' => 5,
            'positive_effects' => ['wealth' => 3], 'negative_effects' => [],
            'available_cooperative' => true, 'available_duel' => false,
        ]);
        $challenge = $this->makeChallenge($character, ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 12]);

        $this->artisan('app:simulate-daily-challenge', ['id' => $challenge->id, '--runs' => 10])
            ->assertSuccessful();

        $challenge->refresh();
        $this->assertSame(10, $challenge->sim_runs);
        $this->assertSame(100, $challenge->sim_success_rate);
        $this->assertNotNull($challenge->sim_computed_at);
    }

    public function test_admin_endpoint_queues_a_simulation_job(): void
    {
        Bus::fake();

        $character = $this->strongAdvisor();
        $challenge = $this->makeChallenge($character, ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 12]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')
            ->postJson("/api/admin/daily-challenges/{$challenge->id}/simulate", ['runs' => 50])
            ->assertStatus(202);

        Bus::assertDispatched(
            SimulateDailyChallengeJob::class,
            fn (SimulateDailyChallengeJob $job): bool => $job->challenge->is($challenge) && $job->runs === 50,
        );
    }

    public function test_admin_endpoint_is_closed_to_non_admins(): void
    {
        $character = $this->strongAdvisor();
        $challenge = $this->makeChallenge($character, ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 12]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'web')
            ->postJson("/api/admin/daily-challenges/{$challenge->id}/simulate")
            ->assertForbidden();
    }
}
