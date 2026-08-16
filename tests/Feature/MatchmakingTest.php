<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MatchmakingEntry;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MatchmakingTest extends TestCase
{
    use RefreshDatabase;

    private function searchingEntryPastTimeout(User $user): MatchmakingEntry
    {
        $entry = MatchmakingEntry::create([
            'user_id' => $user->id,
            'elo_rating' => 1200,
            'total_rounds' => 24,
            'speed_mode' => 'speed',
            'status' => 'searching',
            'bot_timeout' => 45,
        ]);

        // Age the entry well past its bot timeout without tripping the timestamp update.
        MatchmakingEntry::query()->whereKey($entry->id)->update(['created_at' => now()->subSeconds(120)]);

        return $entry;
    }

    public function test_status_matches_with_a_bot_once_the_timeout_has_elapsed(): void
    {
        $bot = User::factory()->create(['is_bot' => true, 'elo_rating' => 1200]);
        $user = User::factory()->create(['elo_rating' => 1200]);
        $entry = $this->searchingEntryPastTimeout($user);

        $this->actingAs($user, 'web')->getJson('/api/matchmaking/status')
            ->assertOk()
            ->assertJsonPath('status', 'matched');

        $gameId = $entry->fresh()->matched_game_id;
        $this->assertNotNull($gameId);
        $this->assertDatabaseHas('game_players', ['game_id' => $gameId, 'user_id' => $user->id, 'player_number' => 1]);
        $this->assertDatabaseHas('game_players', ['game_id' => $gameId, 'user_id' => $bot->id, 'is_bot' => true]);
    }

    public function test_bot_match_survives_a_broadcasting_failure(): void
    {
        // Simulate the websocket server being down: MatchFound is ShouldBroadcastNow, so a
        // throw here previously rolled back the whole match inside the transaction.
        $this->app->bind(BroadcastFactory::class, function () {
            $factory = Mockery::mock(BroadcastFactory::class);
            $factory->shouldReceive('event')->andThrow(new \RuntimeException('websocket down'));

            return $factory;
        });

        User::factory()->create(['is_bot' => true, 'elo_rating' => 1200]);
        $user = User::factory()->create(['elo_rating' => 1200]);
        $entry = $this->searchingEntryPastTimeout($user);

        $this->actingAs($user, 'web')->getJson('/api/matchmaking/status')
            ->assertOk()
            ->assertJsonPath('status', 'matched');

        $this->assertNotNull($entry->fresh()->matched_game_id);
    }
}
