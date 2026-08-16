<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_claim_awards_day_one_coins_and_starts_a_streak(): void
    {
        $user = User::factory()->create(['coins' => 100]);

        $this->actingAs($user, 'web')->postJson('/api/daily-reward/claim')
            ->assertOk()
            ->assertJson(['type' => 'coins', 'amount' => 10, 'day' => 1, 'streak' => 1, 'new_coins' => 110]);

        $this->assertSame(110, $user->fresh()->coins);
        $this->assertSame(1, $user->fresh()->daily_reward_streak);
    }

    public function test_a_second_claim_the_same_day_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->postJson('/api/daily-reward/claim')->assertOk();
        $this->actingAs($user, 'web')->postJson('/api/daily-reward/claim')->assertStatus(422);
    }

    public function test_a_mid_week_day_awards_xp_and_leaves_coins_untouched(): void
    {
        // Streak 3 + a claim yesterday → day 4, which pays XP on the new ladder.
        $user = User::factory()->create([
            'xp' => 0,
            'coins' => 100,
            'daily_reward_streak' => 3,
            'daily_reward_claimed_at' => now()->subDay(),
        ]);

        $this->actingAs($user, 'web')->postJson('/api/daily-reward/claim')
            ->assertOk()
            ->assertJson(['type' => 'xp', 'amount' => 30, 'day' => 4, 'streak' => 4]);

        $this->assertSame(30, $user->fresh()->xp);
        $this->assertSame(100, $user->fresh()->coins);
    }

    public function test_a_lapsed_streak_resets_to_day_one(): void
    {
        $user = User::factory()->create([
            'daily_reward_streak' => 5,
            'daily_reward_claimed_at' => now()->subDays(3),
        ]);

        $this->actingAs($user, 'web')->postJson('/api/daily-reward/claim')
            ->assertOk()
            ->assertJson(['type' => 'coins', 'amount' => 10, 'day' => 1, 'streak' => 1]);
    }
}
