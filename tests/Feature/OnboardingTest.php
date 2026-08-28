<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_game_step_unlocks_and_claiming_grants_coins_once(): void
    {
        $user = User::factory()->create(['coins' => 0, 'level' => 1]);

        // Fresh account: the first step is visible but not yet done, and cannot be claimed.
        $this->actingAs($user)->getJson('/api/onboarding')
            ->assertOk()
            ->assertJsonPath('steps.0.key', 'first_game')
            ->assertJsonPath('steps.0.done', false)
            ->assertJsonPath('complete', false);

        $this->actingAs($user)->postJson('/api/onboarding/first_game/claim')->assertStatus(422);

        // Play one completed game — the step becomes claimable.
        $character = Character::create(['name' => 'Advisor', 'description' => 'x', 'dice' => [[1, 2, 3, 4, 5, 6]]]);
        $game = Game::create([
            'num_players' => 1, 'total_rounds' => 3, 'status' => 'completed',
            'game_mode' => 'single', 'game_type' => 'cooperative', 'user_id' => $user->id,
        ]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $user->id, 'character_id' => $character->id, 'player_number' => 1]);

        $this->actingAs($user)->postJson('/api/onboarding/first_game/claim')
            ->assertOk()
            ->assertJsonPath('steps.0.done', true)
            ->assertJsonPath('steps.0.claimed', true);

        $this->assertSame(50, $user->fresh()->coins);

        // Re-claiming the same step is rejected.
        $this->actingAs($user)->postJson('/api/onboarding/first_game/claim')->assertStatus(422);
    }

    public function test_unknown_step_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/onboarding/not_a_real_step/claim')->assertStatus(422);
    }
}
