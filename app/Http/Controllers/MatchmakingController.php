<?php

namespace App\Http\Controllers;

use App\Events\MatchFound;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\MatchmakingEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchmakingController extends Controller
{
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'total_rounds' => 'required|integer|in:12,24,36,48,60',
        ]);

        $user = $request->user();

        // Check if already searching
        $existing = MatchmakingEntry::where('user_id', $user->id)
            ->where('status', 'searching')
            ->first();

        if ($existing) {
            return response()->json($existing);
        }

        $entry = MatchmakingEntry::create([
            'user_id' => $user->id,
            'elo_rating' => $user->elo_rating ?? 1200,
            'total_rounds' => $request->total_rounds,
            'bot_timeout' => rand(53, 83),
        ]);

        // Immediately attempt to find a match
        $match = $this->findMatch($entry);
        if ($match) {
            $game = $this->createMatch($entry, $match);
            $entry->refresh();
            return response()->json($entry);
        }

        return response()->json($entry);
    }

    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();

        MatchmakingEntry::where('user_id', $user->id)
            ->where('status', 'searching')
            ->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Left matchmaking queue']);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $entry = MatchmakingEntry::where('user_id', $user->id)
            ->whereIn('status', ['searching', 'matched'])
            ->latest()
            ->first();

        if (!$entry) {
            return response()->json(['status' => 'none']);
        }

        if ($entry->status === 'matched') {
            return response()->json($entry);
        }

        // Widen ELO range: +100 per 5 seconds elapsed, max 500
        $elapsed = now()->diffInSeconds($entry->created_at);
        $newRange = min(500, 100 + (int) floor($elapsed / 5) * 100);
        if ($newRange !== $entry->elo_range) {
            $entry->update(['elo_range' => $newRange]);
        }

        // Re-attempt match
        $match = $this->findMatch($entry);
        if ($match) {
            $this->createMatch($entry, $match);
            $entry->refresh();
            return response()->json($entry);
        }

        // Bot fallback: if elapsed >= bot_timeout, match with a bot
        if ($entry->bot_timeout && $elapsed >= $entry->bot_timeout) {
            $this->createBotMatch($entry);
            $entry->refresh();
        }

        return response()->json($entry);
    }

    private function findMatch(MatchmakingEntry $entry): ?MatchmakingEntry
    {
        return MatchmakingEntry::where('status', 'searching')
            ->where('id', '!=', $entry->id)
            ->where('total_rounds', $entry->total_rounds)
            ->where(function ($query) use ($entry) {
                // Both players' ranges must overlap
                $query->whereRaw('elo_rating BETWEEN ? AND ?', [
                    $entry->elo_rating - $entry->elo_range,
                    $entry->elo_rating + $entry->elo_range,
                ])->whereRaw('? BETWEEN elo_rating - elo_range AND elo_rating + elo_range', [
                    $entry->elo_rating,
                ]);
            })
            ->orderByRaw('ABS(elo_rating - ?)', [$entry->elo_rating])
            ->first();
    }

    private function createBotMatch(MatchmakingEntry $entry): void
    {
        // Pick a bot user near the player's ELO
        $bot = User::where('is_bot', true)
            ->orderByRaw('ABS(elo_rating - ?)', [$entry->elo_rating])
            ->first();

        if (!$bot) {
            return;
        }

        DB::transaction(function () use ($entry, $bot) {
            $game = Game::create([
                'status' => 'setup',
                'game_mode' => 'online',
                'game_type' => 'duel',
                'num_players' => 2,
                'total_rounds' => $entry->total_rounds,
                'current_round' => 0,
                'user_id' => $entry->user_id,
            ]);

            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $entry->user_id,
                'player_number' => 1,
            ]);

            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $bot->id,
                'player_number' => 2,
                'is_bot' => true,
            ]);

            $entry->update(['status' => 'matched', 'matched_game_id' => $game->id]);

            broadcast(new MatchFound($entry->user_id, $game->id, $bot->name));
        });
    }

    private function createMatch(MatchmakingEntry $entry1, MatchmakingEntry $entry2): Game
    {
        return DB::transaction(function () use ($entry1, $entry2) {
            $game = Game::create([
                'status' => 'setup',
                'game_mode' => 'online',
                'game_type' => 'duel',
                'num_players' => 2,
                'total_rounds' => $entry1->total_rounds,
                'current_round' => 0,
                'user_id' => $entry1->user_id,
            ]);

            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $entry1->user_id,
                'player_number' => 1,
            ]);

            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $entry2->user_id,
                'player_number' => 2,
            ]);

            $entry1->update(['status' => 'matched', 'matched_game_id' => $game->id]);
            $entry2->update(['status' => 'matched', 'matched_game_id' => $game->id]);

            $user1 = $entry1->user;
            $user2 = $entry2->user;

            broadcast(new MatchFound($entry1->user_id, $game->id, $user2->name));
            broadcast(new MatchFound($entry2->user_id, $game->id, $user1->name));

            return $game;
        });
    }
}
