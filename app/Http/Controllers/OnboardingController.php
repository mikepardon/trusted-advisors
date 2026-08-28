<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DailyChallengeEntry;
use App\Models\Friendship;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The "Getting Started" journey — a first-week quest track that teaches the game and
 * rewards the early milestones that correlate with players sticking around.
 */
class OnboardingController extends Controller
{
    /**
     * @return list<array{key: string, title: string, description: string, reward: array{type: string, amount: int}}>
     */
    private function definitions(): array
    {
        return [
            ['key' => 'first_game', 'title' => 'Take the Throne', 'description' => 'Play your first game.', 'reward' => ['type' => 'coins', 'amount' => 50]],
            ['key' => 'level_2', 'title' => 'Finding Your Feet', 'description' => 'Reach level 2.', 'reward' => ['type' => 'coins', 'amount' => 50]],
            ['key' => 'daily', 'title' => 'Trusted Advisor', 'description' => 'Take on a daily challenge.', 'reward' => ['type' => 'coins', 'amount' => 100]],
            ['key' => 'duel', 'title' => 'Duelist', 'description' => 'Fight a duel.', 'reward' => ['type' => 'coins', 'amount' => 100]],
            ['key' => 'level_4', 'title' => 'Rising Ruler', 'description' => 'Reach level 4.', 'reward' => ['type' => 'xp', 'amount' => 150]],
            ['key' => 'five_games', 'title' => 'Seasoned Ruler', 'description' => 'Play 5 games.', 'reward' => ['type' => 'coins', 'amount' => 150]],
            ['key' => 'friend', 'title' => 'The Inner Circle', 'description' => 'Add a friend.', 'reward' => ['type' => 'coins', 'amount' => 250]],
        ];
    }

    /**
     * Which step goals the player currently meets.
     *
     * @return array<string, bool>
     */
    private function progress(User $user): array
    {
        $gameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');
        $playedGames = Game::whereIn('id', $gameIds)->where('status', 'completed');

        $gamesPlayed = (clone $playedGames)->count();

        return [
            'first_game' => $gamesPlayed >= 1,
            'level_2' => $user->level >= 2,
            'daily' => DailyChallengeEntry::where('user_id', $user->id)->whereNotNull('completed_at')->exists(),
            'duel' => (clone $playedGames)->where('game_type', 'duel')->exists(),
            'level_4' => $user->level >= 4,
            'five_games' => $gamesPlayed >= 5,
            'friend' => Friendship::where('status', 'accepted')
                ->where(fn ($query) => $query->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
                ->exists(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->state($request->user()));
    }

    public function claim(Request $request, string $step): JsonResponse
    {
        $user = $request->user();

        $definition = collect($this->definitions())->firstWhere('key', $step);
        if ($definition === null) {
            return response()->json(['error' => 'Unknown step.'], 422);
        }

        if (($this->progress($user)[$step] ?? false) === false) {
            return response()->json(['error' => 'You have not completed that step yet.'], 422);
        }

        $claimed = $user->onboarding_claims ?? [];
        if (in_array($step, $claimed, true)) {
            return response()->json(['error' => 'Reward already claimed.'], 422);
        }

        DB::transaction(function () use ($user, $definition, $step, $claimed): void {
            $reward = $definition['reward'];
            if ($reward['type'] === 'coins') {
                $user->coins += $reward['amount'];
                $user->recordCoinTransaction($reward['amount'], 'earn', 'onboarding', null, "Getting Started: {$definition['title']}");
            } else {
                $user->xp += $reward['amount'];
                $user->level = User::calculateLevel($user->xp);
            }

            $claimed[] = $step;
            $user->onboarding_claims = array_values($claimed);
            $user->save();
        });

        return response()->json($this->state($user->fresh()));
    }

    /**
     * @return array{steps: list<array<string, mixed>>, complete: bool}
     */
    private function state(User $user): array
    {
        $done = $this->progress($user);
        $claimed = $user->onboarding_claims ?? [];

        $steps = collect($this->definitions())
            ->map(fn (array $definition): array => [
                ...$definition,
                'done' => $done[$definition['key']] ?? false,
                'claimed' => in_array($definition['key'], $claimed, true),
            ])
            ->values()
            ->all();

        return [
            'steps' => $steps,
            // Once every step is claimed the journey is finished and the UI can hide it.
            'complete' => collect($steps)->every(fn (array $step): bool => $step['claimed']),
        ];
    }
}
