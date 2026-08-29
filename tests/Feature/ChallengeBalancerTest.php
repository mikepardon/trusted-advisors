<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Character;
use App\Models\DailyChallenge;
use App\Services\ChallengeBalancer;
use App\Services\ChallengeSimulator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeBalancerTest extends TestCase
{
    use RefreshDatabase;

    private function strongAdvisor(): Character
    {
        \App\Models\Event::create(['title' => 'Calm', 'effect' => 'x', 'available_cooperative' => true, 'available_duel' => false]);

        return Character::create([
            'name' => 'Champion',
            'description' => 'x',
            'dice' => [[6, 6, 6, 6, 6, 6], [6, 6, 6, 6, 6, 6], [6, 6, 6, 6, 6, 6]],
        ]);
    }

    private function makeChallenge(Character $character, array $goal, array $houseRules = []): DailyChallenge
    {
        return DailyChallenge::create([
            'date' => Carbon::today(),
            'title' => 'Sim Target',
            'description' => 'x',
            'criteria' => [
                'mode' => 'cooperative',
                'rounds' => 12,
                'start' => ['all' => 8],
                'goal' => $goal,
                'seed_character_id' => $character->id,
                'house_rules' => $houseRules,
            ],
            'reward_xp' => 100,
            'is_manual' => false,
        ]);
    }

    public function test_an_impossible_challenge_is_tuned_until_a_bot_can_win_it(): void
    {
        $character = $this->strongAdvisor();

        // As shipped this is unwinnable: happiness drains 4 a month toward a distant wealth
        // target of 20, collapsing before wealth ever arrives (see ChallengeSimulatorTest).
        for ($i = 1; $i <= 4; $i++) {
            Card::create([
                'title' => "Trap {$i}", 'description' => 'x', 'sort_order' => $i, 'difficulty' => 5,
                'positive_effects' => ['wealth' => 2], 'negative_effects' => ['happiness' => -4],
                'available_cooperative' => true, 'available_duel' => false,
            ]);
        }

        $challenge = $this->makeChallenge($character, ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 20]);

        $result = app(ChallengeBalancer::class)->balance($challenge);

        // The balancer had to intervene, and the outcome is now genuinely winnable.
        $this->assertTrue($result['winnable']);
        $this->assertGreaterThanOrEqual(ChallengeSimulator::MIN_WINNABLE_RATE, $result['success_rate']);
        $this->assertGreaterThan(0, $result['steps']);

        // The result is cached on the row, and the criteria were actually eased.
        $challenge->refresh();
        $this->assertGreaterThanOrEqual(ChallengeSimulator::MIN_WINNABLE_RATE, $challenge->sim_success_rate);
        $this->assertTrue(($challenge->criteria['house_rules']['no_negative_effects'] ?? false));
    }

    public function test_an_already_winnable_challenge_is_left_untouched(): void
    {
        $character = $this->strongAdvisor();
        for ($i = 1; $i <= 4; $i++) {
            Card::create([
                'title' => "Boon {$i}", 'description' => 'x', 'sort_order' => $i, 'difficulty' => 5,
                'positive_effects' => ['wealth' => 3], 'negative_effects' => [],
                'available_cooperative' => true, 'available_duel' => false,
            ]);
        }

        $goal = ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 12];
        $challenge = $this->makeChallenge($character, $goal);

        $result = app(ChallengeBalancer::class)->balance($challenge);

        // Trivially winnable already — no easing steps, and the goal is preserved verbatim.
        $this->assertSame(0, $result['steps']);
        $this->assertSame(100, $result['success_rate']);
        $this->assertSame($goal, $challenge->fresh()->criteria['goal']);
    }

    public function test_the_generator_produces_a_challenge_a_bot_can_win(): void
    {
        $character = $this->strongAdvisor();
        // Strong boons so whatever goal the date lands on, the run is comfortably winnable.
        for ($i = 1; $i <= 6; $i++) {
            Card::create([
                'title' => "Boon {$i}", 'description' => 'x', 'sort_order' => $i, 'difficulty' => 5,
                'positive_effects' => ['wealth' => 3, 'influence' => 3, 'security' => 3, 'religion' => 3, 'food' => 3, 'happiness' => 3],
                'negative_effects' => [],
                'available_cooperative' => true, 'available_duel' => false,
            ]);
        }

        $this->artisan('app:generate-daily-challenge', ['--date' => Carbon::today()->addDays(15)->toDateString()])
            ->assertSuccessful();

        $challenge = DailyChallenge::whereDate('date', Carbon::today()->addDays(15))->first();
        $this->assertNotNull($challenge);
        $this->assertNotNull($challenge->sim_computed_at, 'The generator should have verified winnability.');
        $this->assertGreaterThanOrEqual(ChallengeSimulator::MIN_WINNABLE_RATE, $challenge->sim_success_rate);
    }

    public function test_the_rebuild_command_fixes_an_existing_impossible_challenge(): void
    {
        $character = $this->strongAdvisor();
        for ($i = 1; $i <= 4; $i++) {
            Card::create([
                'title' => "Trap {$i}", 'description' => 'x', 'sort_order' => $i, 'difficulty' => 5,
                'positive_effects' => ['wealth' => 2], 'negative_effects' => ['happiness' => -4],
                'available_cooperative' => true, 'available_duel' => false,
            ]);
        }

        // A "raise every stat" challenge — the exact shape that shipped at 0%.
        $challenge = $this->makeChallenge($character, [
            'type' => 'stat_threshold_all',
            'targets' => ['wealth' => 13, 'influence' => 13, 'security' => 13, 'religion' => 13, 'food' => 13, 'happiness' => 13],
        ]);

        $this->artisan('app:rebuild-daily-challenges', ['id' => $challenge->id])->assertSuccessful();

        $challenge->refresh();
        $this->assertNotNull($challenge->sim_success_rate);
        $this->assertGreaterThanOrEqual(ChallengeSimulator::MIN_WINNABLE_RATE, $challenge->sim_success_rate);
    }
}
