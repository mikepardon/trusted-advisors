<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\NextRoundStarted;
use Tests\TestCase;

class NextRoundStartedBroadcastTest extends TestCase
{
    public function test_small_game_state_is_broadcast_in_full(): void
    {
        $gameData = [
            'game' => ['id' => 1, 'current_round' => 3],
            'current_round' => 3,
            'round_phase' => 'selecting',
        ];

        $this->assertSame($gameData, (new NextRoundStarted(1, $gameData))->broadcastWith());
    }

    public function test_oversized_game_state_is_replaced_with_a_refetch_marker(): void
    {
        $gameData = [
            'game' => ['id' => 1, 'current_round' => 3],
            'current_round' => 3,
            'round_phase' => 'selecting',
            // Stand-in for a full six-player payload (players, items, curses).
            'players' => array_fill(0, 200, ['id' => 1, 'notes' => str_repeat('x', 100)]),
        ];

        $payload = (new NextRoundStarted(1, $gameData))->broadcastWith();

        $this->assertSame([
            'refetch' => true,
            'current_round' => 3,
            'round_phase' => 'selecting',
        ], $payload);
        $this->assertLessThanOrEqual(
            NextRoundStarted::MAX_PAYLOAD_BYTES,
            strlen((string) json_encode($payload)),
        );
    }
}
