<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Models\UserCharacter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterUpgradeCostTest extends TestCase
{
    use RefreshDatabase;

    private function character(): Character
    {
        return Character::create([
            'name' => 'Test Advisor',
            'description' => 'A test advisor.',
            'dice' => [1, 2, 3, 4, 5, 6],
        ]);
    }

    public function test_levels_one_to_three_cost_no_coins(): void
    {
        $userCharacter = new UserCharacter(['incarnation' => 1]);

        $this->assertSame(0, $userCharacter->coinCostForLevel(1));
        $this->assertSame(0, $userCharacter->coinCostForLevel(2));
        $this->assertSame(0, $userCharacter->coinCostForLevel(3));
    }

    public function test_level_costs_follow_the_configured_curve_at_first_incarnation(): void
    {
        $userCharacter = new UserCharacter(['incarnation' => 1]);

        $this->assertSame(100, $userCharacter->coinCostForLevel(4));
        $this->assertSame(200, $userCharacter->coinCostForLevel(5));
        $this->assertSame(350, $userCharacter->coinCostForLevel(6));
        $this->assertSame(550, $userCharacter->coinCostForLevel(7));
        $this->assertSame(800, $userCharacter->coinCostForLevel(8));
    }

    public function test_immortalise_costs_two_thousand_at_first_incarnation(): void
    {
        $userCharacter = new UserCharacter(['incarnation' => 1]);

        $this->assertSame(2000, $userCharacter->immortaliseCost());
    }

    public function test_costs_scale_by_1_point_2_per_incarnation(): void
    {
        $userCharacter = new UserCharacter(['incarnation' => 2]);

        $this->assertSame(120, $userCharacter->coinCostForLevel(4)); // 100 × 1.2
        $this->assertSame(2400, $userCharacter->immortaliseCost()); // 2000 × 1.2
    }

    public function test_immortalising_deducts_the_cost_and_resets_the_character(): void
    {
        $user = User::factory()->create(['coins' => 3000]);
        $userCharacter = UserCharacter::create([
            'user_id' => $user->id,
            'character_id' => $this->character()->id,
            'xp' => 0,
            'level' => 8,
            'incarnation' => 1,
        ]);

        $this->actingAs($user, 'web')->postJson("/api/my-advisors/{$userCharacter->id}/immortalise")
            ->assertOk()
            ->assertJsonPath('new_coins', 1000);

        $this->assertSame(1000, $user->fresh()->coins);
        $userCharacter->refresh();
        $this->assertSame(2, $userCharacter->incarnation);
        $this->assertSame(1, $userCharacter->level);
    }

    public function test_immortalising_is_rejected_without_enough_coins(): void
    {
        $user = User::factory()->create(['coins' => 500]);
        $userCharacter = UserCharacter::create([
            'user_id' => $user->id,
            'character_id' => $this->character()->id,
            'xp' => 0,
            'level' => 8,
            'incarnation' => 1,
        ]);

        $this->actingAs($user, 'web')->postJson("/api/my-advisors/{$userCharacter->id}/immortalise")
            ->assertStatus(422);

        $this->assertSame(500, $user->fresh()->coins);
    }
}
