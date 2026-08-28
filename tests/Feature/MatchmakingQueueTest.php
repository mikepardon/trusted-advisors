<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\MatchmakingEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchmakingQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_background_matcher_pairs_two_waiting_correspondence_players(): void
    {
        $a = User::factory()->create(['elo_rating' => 1200]);
        $b = User::factory()->create(['elo_rating' => 1210]);

        foreach ([$a, $b] as $user) {
            MatchmakingEntry::create([
                'user_id' => $user->id, 'elo_rating' => $user->elo_rating,
                'total_rounds' => 24, 'speed_mode' => 'daily', 'status' => 'searching', 'elo_range' => 0,
            ]);
        }

        $this->artisan('app:process-matchmaking')->assertSuccessful();

        $this->assertSame('matched', MatchmakingEntry::where('user_id', $a->id)->first()->status);
        $this->assertSame('matched', MatchmakingEntry::where('user_id', $b->id)->first()->status);

        // Exactly one online duel game was created with both players in it.
        $this->assertSame(1, Game::where('game_type', 'duel')->where('game_mode', 'online')->count());
        $game = Game::where('game_type', 'duel')->first();
        $this->assertSame(2, GamePlayer::where('game_id', $game->id)->count());
    }

    public function test_lone_correspondence_player_is_bot_matched_after_the_timeout(): void
    {
        User::factory()->create(['is_bot' => true, 'elo_rating' => 1200]);
        $player = User::factory()->create(['elo_rating' => 1200]);

        $entry = MatchmakingEntry::create([
            'user_id' => $player->id, 'elo_rating' => 1200,
            'total_rounds' => 24, 'speed_mode' => 'daily', 'status' => 'searching', 'elo_range' => 0,
        ]);
        // Age the entry past the correspondence bot timeout.
        MatchmakingEntry::where('id', $entry->id)->update(['created_at' => now()->subSeconds(200)]);

        $this->artisan('app:process-matchmaking')->assertSuccessful();

        $this->assertSame('matched', $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->matched_game_id);
    }

    public function test_a_fresh_lone_player_is_left_waiting(): void
    {
        $player = User::factory()->create(['elo_rating' => 1200]);
        MatchmakingEntry::create([
            'user_id' => $player->id, 'elo_rating' => 1200,
            'total_rounds' => 24, 'speed_mode' => 'daily', 'status' => 'searching', 'elo_range' => 0,
        ]);

        $this->artisan('app:process-matchmaking')->assertSuccessful();

        $this->assertSame('searching', MatchmakingEntry::where('user_id', $player->id)->first()->status);
    }
}
