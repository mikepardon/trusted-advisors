<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Character;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Season;
use App\Models\User;

/**
 * Test harness for exercising the real game-completion flow. Builds genuine Game +
 * GamePlayer + Character rows (no mocks) so tests can drive GameCompletionService
 * through its actual composition entry point, processCompletion().
 */
trait InteractsWithGames
{
    protected function makeCharacter(array $overrides = []): Character
    {
        return Character::create(array_merge([
            'name' => 'Test Advisor',
            'description' => 'An advisor used in tests.',
            'dice' => [[1, 2, 3, 4, 5, 6]],
        ], $overrides));
    }

    protected function makeActiveSeason(): Season
    {
        return Season::create([
            'name' => 'Test Season',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    /**
     * A completed single-human game ready for processCompletion(). Defaults to an
     * offline single-player cooperative loss; override mode/type/win as needed.
     *
     * @param array{mode?: string, type?: string, win?: bool, is_custom?: bool, winner_player_number?: int|null} $options
     */
    protected function completedGameFor(User $user, array $options = []): Game
    {
        $character = $this->makeCharacter();

        $game = Game::create([
            'num_players' => 1,
            'total_rounds' => 12,
            'status' => 'completed',
            'game_mode' => $options['mode'] ?? 'single',
            'game_type' => $options['type'] ?? 'cooperative',
            'is_custom' => $options['is_custom'] ?? false,
            'win' => $options['win'] ?? false,
            'winner_player_number' => $options['winner_player_number'] ?? null,
            'user_id' => $user->id,
        ]);

        GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'player_number' => 1,
            'is_bot' => false,
        ]);

        return $game->fresh();
    }
}
