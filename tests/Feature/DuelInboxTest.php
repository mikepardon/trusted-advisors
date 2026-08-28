<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuelInboxTest extends TestCase
{
    use RefreshDatabase;

    private function duel(User $me, User $opponent, string $phase, int $offerer): Game
    {
        $game = Game::create([
            'status' => 'active', 'game_mode' => 'online', 'game_type' => 'duel',
            'num_players' => 2, 'total_rounds' => 12, 'current_round' => 3,
            'user_id' => $me->id, 'turn_time_limit' => 86400,
            'offerer_player_number' => $offerer, 'duel_phase' => $phase, 'turn_started_at' => now(),
        ]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $me->id, 'player_number' => 2]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $opponent->id, 'player_number' => 1]);

        return $game;
    }

    public function test_inbox_flags_a_correspondence_duel_as_your_turn(): void
    {
        $me = User::factory()->create();
        $opponent = User::factory()->create(['name' => 'Rival']);
        // Offerer is player 1, phase 'choosing' → the chooser (player 2 = me) must act.
        $this->duel($me, $opponent, 'choosing', 1);

        $this->actingAs($me)->getJson('/api/duels/active')
            ->assertOk()
            ->assertJsonPath('your_turn_count', 1)
            ->assertJsonPath('duels.0.opponent', 'Rival')
            ->assertJsonPath('duels.0.is_my_turn', true)
            ->assertJsonPath('duels.0.is_correspondence', true);
    }

    public function test_inbox_shows_waiting_when_it_is_the_opponents_turn(): void
    {
        $me = User::factory()->create();
        $opponent = User::factory()->create();
        // Offerer is player 1, phase 'offering' → the offerer (player 1 = opponent) must act.
        $this->duel($me, $opponent, 'offering', 1);

        $this->actingAs($me)->getJson('/api/duels/active')
            ->assertOk()
            ->assertJsonPath('your_turn_count', 0)
            ->assertJsonPath('duels.0.is_my_turn', false);
    }
}
